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

$page_title = 'Performance Management - HRMS';

// Create performance management tables if not exist
$createPerformanceReviewsTable = "
CREATE TABLE IF NOT EXISTS performance_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    review_period_start DATE NOT NULL,
    review_period_end DATE NOT NULL,
    review_type ENUM('quarterly', 'half_yearly', 'annual', 'probation') DEFAULT 'quarterly',
    overall_rating DECIMAL(3,2) DEFAULT NULL,
    goals_achievement_rating DECIMAL(3,2) DEFAULT NULL,
    skills_rating DECIMAL(3,2) DEFAULT NULL,
    communication_rating DECIMAL(3,2) DEFAULT NULL,
    teamwork_rating DECIMAL(3,2) DEFAULT NULL,
    leadership_rating DECIMAL(3,2) DEFAULT NULL,
    initiative_rating DECIMAL(3,2) DEFAULT NULL,
    punctuality_rating DECIMAL(3,2) DEFAULT NULL,
    quality_of_work_rating DECIMAL(3,2) DEFAULT NULL,
    strengths TEXT,
    areas_for_improvement TEXT,
    reviewer_comments TEXT,
    employee_comments TEXT,
    action_plan TEXT,
    next_review_date DATE NULL,
    status ENUM('draft', 'submitted', 'reviewed', 'completed', 'cancelled') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (reviewer_id) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createPerformanceReviewsTable);

$createGoalsTable = "
CREATE TABLE IF NOT EXISTS employee_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    goal_title VARCHAR(255) NOT NULL,
    goal_description TEXT,
    goal_category ENUM('professional', 'personal', 'skill_development', 'project', 'kpi') DEFAULT 'professional',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    target_date DATE NOT NULL,
    start_date DATE NOT NULL,
    completion_percentage DECIMAL(5,2) DEFAULT 0,
    status ENUM('not_started', 'in_progress', 'completed', 'overdue', 'cancelled') DEFAULT 'not_started',
    assigned_by INT NOT NULL,
    progress_notes TEXT,
    completion_date DATE NULL,
    achievement_rating DECIMAL(3,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (assigned_by) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createGoalsTable);

$createPerformanceMetricsTable = "
CREATE TABLE IF NOT EXISTS performance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    metric_name VARCHAR(255) NOT NULL,
    metric_value DECIMAL(10,2) NOT NULL,
    target_value DECIMAL(10,2) NOT NULL,
    unit VARCHAR(50) DEFAULT '',
    measurement_period DATE NOT NULL,
    metric_category ENUM('productivity', 'quality', 'efficiency', 'revenue', 'customer_satisfaction', 'other') DEFAULT 'productivity',
    recorded_by INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (recorded_by) REFERENCES employees(employee_id)
)";
mysqli_query($conn, $createPerformanceMetricsTable);

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_review':
                $employee_id = intval($_POST['employee_id']);
                $reviewer_id = $_SESSION['user_id'] ?? 1;
                $review_period_start = $_POST['review_period_start'];
                $review_period_end = $_POST['review_period_end'];
                $review_type = $_POST['review_type'];
                $next_review_date = $_POST['next_review_date'];
                
                $query = "
                    INSERT INTO performance_reviews (
                        employee_id, reviewer_id, review_period_start, review_period_end, 
                        review_type, next_review_date, status
                    ) VALUES (?, ?, ?, ?, ?, ?, 'draft')
                ";
                
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'iissss', $employee_id, $reviewer_id, $review_period_start, 
                                     $review_period_end, $review_type, $next_review_date);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Performance review created successfully!";
                } else {
                    $error_message = "Error creating performance review: " . mysqli_error($conn);
                }
                break;
                
            case 'add_goal':
                $employee_id = intval($_POST['employee_id']);
                $goal_title = $_POST['goal_title'];
                $goal_description = $_POST['goal_description'];
                $goal_category = $_POST['goal_category'];
                $priority = $_POST['priority'];
                $start_date = $_POST['start_date'];
                $target_date = $_POST['target_date'];
                $assigned_by = $_SESSION['user_id'] ?? 1;
                
                $query = "
                    INSERT INTO employee_goals (
                        employee_id, goal_title, goal_description, goal_category, 
                        priority, start_date, target_date, assigned_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ";
                
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'issssssi', $employee_id, $goal_title, $goal_description, 
                                     $goal_category, $priority, $start_date, $target_date, $assigned_by);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Goal added successfully!";
                } else {
                    $error_message = "Error adding goal: " . mysqli_error($conn);
                }
                break;
                
            case 'update_goal_progress':
                $goal_id = intval($_POST['goal_id']);
                $completion_percentage = floatval($_POST['completion_percentage']);
                $status = $_POST['status'];
                $progress_notes = $_POST['progress_notes'];
                
                $updateData = "completion_percentage = $completion_percentage, status = '$status', progress_notes = '$progress_notes'";
                if ($status === 'completed') {
                    $updateData .= ", completion_date = CURDATE()";
                }
                
                $query = "UPDATE employee_goals SET $updateData WHERE id = $goal_id";
                
                if (mysqli_query($conn, $query)) {
                    $success_message = "Goal progress updated successfully!";
                } else {
                    $error_message = "Error updating goal progress: " . mysqli_error($conn);
                }
                break;
                
            case 'add_metric':
                $employee_id = intval($_POST['employee_id']);
                $metric_name = $_POST['metric_name'];
                $metric_value = floatval($_POST['metric_value']);
                $target_value = floatval($_POST['target_value']);
                $unit = $_POST['unit'];
                $measurement_period = $_POST['measurement_period'];
                $metric_category = $_POST['metric_category'];
                $notes = $_POST['notes'];
                $recorded_by = $_SESSION['user_id'] ?? 1;
                
                $query = "
                    INSERT INTO performance_metrics (
                        employee_id, metric_name, metric_value, target_value, unit,
                        measurement_period, metric_category, recorded_by, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'isddsssis', $employee_id, $metric_name, $metric_value, 
                                     $target_value, $unit, $measurement_period, $metric_category, $recorded_by, $notes);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Performance metric added successfully!";
                } else {
                    $error_message = "Error adding performance metric: " . mysqli_error($conn);
                }
                break;
        }
    }
}

// Get performance statistics
$stats = [
    'active_reviews' => 0,
    'completed_reviews_this_month' => 0,
    'active_goals' => 0,
    'avg_performance_rating' => 0
];

$statsQuery = "
    SELECT 
        SUM(CASE WHEN pr.status IN ('draft', 'submitted', 'reviewed') THEN 1 ELSE 0 END) as active_reviews,
        SUM(CASE WHEN pr.status = 'completed' AND MONTH(pr.updated_at) = MONTH(CURDATE()) 
                 AND YEAR(pr.updated_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as completed_reviews_this_month,
        AVG(CASE WHEN pr.overall_rating IS NOT NULL THEN pr.overall_rating END) as avg_performance_rating
    FROM performance_reviews pr
";

$result = mysqli_query($conn, $statsQuery);
if ($result) {
    $stats = array_merge($stats, mysqli_fetch_assoc($result));
}

// Get active goals count
$goalsQuery = "SELECT COUNT(*) as active_goals FROM employee_goals WHERE status IN ('not_started', 'in_progress')";
$result = mysqli_query($conn, $goalsQuery);
if ($result) {
    $goals = mysqli_fetch_assoc($result);
    $stats['active_goals'] = $goals['active_goals'];
}

// Get recent reviews
$recentReviews = [];
$query = "
    SELECT pr.*, e.name as employee_name, e.employee_code, r.name as reviewer_name
    FROM performance_reviews pr
    JOIN employees e ON pr.employee_id = e.employee_id
    JOIN employees r ON pr.reviewer_id = r.employee_id
    ORDER BY pr.created_at DESC
    LIMIT 10
";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recentReviews[] = $row;
    }
}

// Get active goals
$activeGoals = [];
$query = "
    SELECT eg.*, e.name as employee_name, e.employee_code, a.name as assigned_by_name
    FROM employee_goals eg
    JOIN employees e ON eg.employee_id = e.employee_id
    JOIN employees a ON eg.assigned_by = a.employee_id
    WHERE eg.status IN ('not_started', 'in_progress')
    ORDER BY eg.target_date ASC
    LIMIT 15
";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $activeGoals[] = $row;
    }
}

// Get employees for dropdowns
$employees = [];
$query = "SELECT employee_id, name, employee_code, position FROM employees WHERE status = 'active' ORDER BY name";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $employees[] = $row;
    }
}

// Get performance trends data
$performanceTrends = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('n', strtotime("-$i months"));
    $year = date('Y', strtotime("-$i months"));
    $monthName = date('M Y', strtotime("-$i months"));
    
    $trendQuery = "
        SELECT 
            COUNT(*) as review_count,
            AVG(overall_rating) as avg_rating
        FROM performance_reviews 
        WHERE MONTH(created_at) = $month AND YEAR(created_at) = $year
        AND overall_rating IS NOT NULL
    ";
    
    $result = mysqli_query($conn, $trendQuery);
    if ($result) {
        $trend = mysqli_fetch_assoc($result);
        $performanceTrends[] = [
            'month' => $monthName,
            'count' => $trend['review_count'],
            'rating' => $trend['avg_rating'] ?? 0
        ];
    }
}

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
                    <i class="bi bi-graph-up-arrow text-primary me-3"></i>Performance Management
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Track employee performance, set goals, and conduct comprehensive reviews</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="exportPerformanceReport()">
                    <i class="bi bi-download"></i> Export Report
                </button>
                <button class="btn btn-outline-success" onclick="viewPerformanceAnalytics()">
                    <i class="bi bi-bar-chart"></i> Analytics
                </button>
                <div class="btn-group">
                    <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-plus-circle"></i> New
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#createReviewModal">
                            <i class="bi bi-clipboard-check"></i> Performance Review
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addGoalModal">
                            <i class="bi bi-bullseye"></i> Employee Goal
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addMetricModal">
                            <i class="bi bi-graph-up"></i> Performance Metric
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= intval($stats['active_reviews']) ?></h3>
                                <p class="card-text opacity-90">Active Reviews</p>
                                <small class="opacity-75">In progress</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-clipboard-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= intval($stats['completed_reviews_this_month']) ?></h3>
                                <p class="card-text opacity-90">Completed This Month</p>
                                <small class="opacity-75"><?= date('F Y') ?></small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= intval($stats['active_goals']) ?></h3>
                                <p class="card-text opacity-90">Active Goals</p>
                                <small class="opacity-75">Being tracked</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-bullseye"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-gradient-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="card-title h2 mb-2"><?= number_format($stats['avg_performance_rating'], 1) ?></h3>
                                <p class="card-text opacity-90">Avg Performance</p>
                                <small class="opacity-75">Out of 5.0</small>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-star"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row g-4">
            <!-- Recent Reviews & Goals -->
            <div class="col-xl-8">
                <!-- Performance Reviews -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clipboard-check text-primary"></i> Recent Performance Reviews
                        </h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary active">All</button>
                            <button class="btn btn-outline-secondary">Pending</button>
                            <button class="btn btn-outline-secondary">Completed</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="reviewsTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Review Period</th>
                                        <th>Type</th>
                                        <th>Rating</th>
                                        <th>Reviewer</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentReviews as $review): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($review['employee_name']) ?></h6>
                                                    <small class="text-muted"><?= htmlspecialchars($review['employee_code']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?= date('M d', strtotime($review['review_period_start'])) ?> - 
                                                <?= date('M d, Y', strtotime($review['review_period_end'])) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $review['review_type'])) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($review['overall_rating']): ?>
                                                    <div class="d-flex align-items-center">
                                                        <span class="me-2"><?= number_format($review['overall_rating'], 1) ?></span>
                                                        <div class="rating-stars">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="bi bi-star<?= $i <= round($review['overall_rating']) ? '-fill text-warning' : ' text-muted' ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Not rated</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($review['reviewer_name']) ?></td>
                                            <td>
                                                <?php
                                                $statusClass = match($review['status']) {
                                                    'draft' => 'secondary',
                                                    'submitted' => 'warning',
                                                    'reviewed' => 'info',
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($review['status']) ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-info" 
                                                            onclick="viewReview(<?= $review['id'] ?>)"
                                                            data-bs-toggle="tooltip" title="View Review">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <?php if ($review['status'] === 'draft'): ?>
                                                        <button class="btn btn-outline-primary" 
                                                                onclick="editReview(<?= $review['id'] ?>)"
                                                                data-bs-toggle="tooltip" title="Edit Review">
                                                            <i class="bi bi-pencil"></i>
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
                </div>

                <!-- Active Goals -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-bullseye text-success"></i> Active Employee Goals
                        </h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addGoalModal">
                            <i class="bi bi-plus-circle"></i> Add Goal
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="goalsTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Goal</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Progress</th>
                                        <th>Target Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeGoals as $goal): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($goal['employee_name']) ?></h6>
                                                    <small class="text-muted"><?= htmlspecialchars($goal['employee_code']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <h6 class="mb-0"><?= htmlspecialchars($goal['goal_title']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars(substr($goal['goal_description'], 0, 50)) ?>...</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $goal['goal_category'])) ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $priorityClass = match($goal['priority']) {
                                                    'low' => 'success',
                                                    'medium' => 'warning',
                                                    'high' => 'danger',
                                                    'critical' => 'dark',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <span class="badge bg-<?= $priorityClass ?>"><?= ucfirst($goal['priority']) ?></span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?= $goal['completion_percentage'] ?>%"
                                                         aria-valuenow="<?= $goal['completion_percentage'] ?>" 
                                                         aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <small class="text-muted"><?= number_format($goal['completion_percentage'], 1) ?>%</small>
                                            </td>
                                            <td>
                                                <?= date('M d, Y', strtotime($goal['target_date'])) ?>
                                                <?php if (strtotime($goal['target_date']) < time() && $goal['status'] !== 'completed'): ?>
                                                    <br><small class="text-danger">Overdue</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-info" 
                                                            onclick="viewGoal(<?= $goal['id'] ?>)"
                                                            data-bs-toggle="tooltip" title="View Goal">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-success" 
                                                            onclick="updateGoalProgress(<?= $goal['id'] ?>)"
                                                            data-bs-toggle="tooltip" title="Update Progress">
                                                        <i class="bi bi-arrow-up-circle"></i>
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
                <!-- Performance Trends Chart -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-graph-up text-info"></i> Performance Trends
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceTrendChart" style="height: 250px;"></canvas>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-lightning text-warning"></i> Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createReviewModal">
                                <i class="bi bi-clipboard-check"></i> Create Review
                            </button>
                            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addGoalModal">
                                <i class="bi bi-bullseye"></i> Set Goal
                            </button>
                            <button class="btn btn-outline-info" onclick="viewPerformanceReports()">
                                <i class="bi bi-bar-chart"></i> View Reports
                            </button>
                            <button class="btn btn-outline-warning" onclick="viewGoalAnalytics()">
                                <i class="bi bi-graph-down"></i> Goal Analytics
                            </button>
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
                <h5 class="modal-title">Create Performance Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_review">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee *</label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?= $employee['employee_id'] ?>">
                                        <?= htmlspecialchars($employee['name']) ?> - <?= htmlspecialchars($employee['employee_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Review Type *</label>
                            <select class="form-select" name="review_type" required>
                                <option value="quarterly">Quarterly Review</option>
                                <option value="half_yearly">Half-Yearly Review</option>
                                <option value="annual">Annual Review</option>
                                <option value="probation">Probation Review</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Review Period Start *</label>
                            <input type="date" class="form-control" name="review_period_start" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Review Period End *</label>
                            <input type="date" class="form-control" name="review_period_end" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Next Review Date</label>
                            <input type="date" class="form-control" name="next_review_date">
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Review will be created in draft status. You can complete the ratings and feedback later.
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

<!-- Add Goal Modal -->
<div class="modal fade" id="addGoalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Employee Goal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_goal">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee *</label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?= $employee['employee_id'] ?>">
                                        <?= htmlspecialchars($employee['name']) ?> - <?= htmlspecialchars($employee['employee_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Goal Category *</label>
                            <select class="form-select" name="goal_category" required>
                                <option value="professional">Professional Development</option>
                                <option value="personal">Personal Development</option>
                                <option value="skill_development">Skill Development</option>
                                <option value="project">Project Goals</option>
                                <option value="kpi">KPI/Performance</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Goal Title *</label>
                            <input type="text" class="form-control" name="goal_title" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Goal Description</label>
                            <textarea class="form-control" name="goal_description" rows="3"></textarea>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Priority *</label>
                            <select class="form-select" name="priority" required>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Target Date *</label>
                            <input type="date" class="form-control" name="target_date" required>
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
<div class="modal fade" id="addMetricModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Performance Metric</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_metric">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Employee *</label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?= $employee['employee_id'] ?>">
                                        <?= htmlspecialchars($employee['name']) ?> - <?= htmlspecialchars($employee['employee_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Metric Name *</label>
                            <input type="text" class="form-control" name="metric_name" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Actual Value *</label>
                            <input type="number" class="form-control" name="metric_value" step="0.01" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Target Value *</label>
                            <input type="number" class="form-control" name="target_value" step="0.01" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Unit</label>
                            <input type="text" class="form-control" name="unit" placeholder="e.g., %, hours, units">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="metric_category">
                                <option value="productivity">Productivity</option>
                                <option value="quality">Quality</option>
                                <option value="efficiency">Efficiency</option>
                                <option value="revenue">Revenue</option>
                                <option value="customer_satisfaction">Customer Satisfaction</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Measurement Period *</label>
                            <input type="date" class="form-control" name="measurement_period" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
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

<style>
.stat-card {
    border: none;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.stat-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
}

.bg-gradient-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
}

.gradient-text {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.animate-fade-in-up {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.rating-stars i {
    font-size: 0.9rem;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize DataTables
document.addEventListener('DOMContentLoaded', function() {
    $('#reviewsTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [6] }
        ]
    });
    
    $('#goalsTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[5, 'asc']],
        columnDefs: [
            { orderable: false, targets: [6] }
        ]
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize performance trend chart
    initPerformanceTrendChart();
});

// Performance Trend Chart
function initPerformanceTrendChart() {
    const ctx = document.getElementById('performanceTrendChart').getContext('2d');
    const trendData = <?= json_encode($performanceTrends) ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendData.map(d => d.month),
            datasets: [
                {
                    label: 'Avg Rating',
                    data: trendData.map(d => d.rating),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Review Count',
                    data: trendData.map(d => d.count),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
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
                    type: 'linear',
                    display: true,
                    position: 'left',
                    max: 5,
                    min: 0
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}

// View Review Function
function viewReview(reviewId) {
    window.open('performance_review_details.php?id=' + reviewId, '_blank');
}

// Edit Review Function
function editReview(reviewId) {
    window.open('edit_performance_review.php?id=' + reviewId, '_blank');
}

// View Goal Function
function viewGoal(goalId) {
    window.open('goal_details.php?id=' + goalId, '_blank');
}

// Update Goal Progress Function
function updateGoalProgress(goalId) {
    window.open('update_goal_progress.php?id=' + goalId, '_blank');
}

// Export Performance Report
function exportPerformanceReport() {
    window.open('api/export_performance_report.php', '_blank');
}

// View Performance Analytics
function viewPerformanceAnalytics() {
    window.open('performance_analytics.php', '_blank');
}

// View Performance Reports
function viewPerformanceReports() {
    window.open('performance_reports.php', '_blank');
}

// View Goal Analytics
function viewGoalAnalytics() {
    window.open('goal_analytics.php', '_blank');
}
</script>

<?php if (!isset($root_path)) 
include '../layouts/footer.php'; ?>
