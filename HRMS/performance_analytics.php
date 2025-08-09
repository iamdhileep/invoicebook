<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$page_title = 'Performance Analytics';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_department_analytics':
            try {
                $department = $_POST['department'] ?? 'all';
                $period = $_POST['period'] ?? '12';
                
                $where_clause = "";
                if ($department !== 'all') {
                    $where_clause = "AND e.department_name = '" . $conn->real_escape_string($department) . "'";
                }
                
                // Performance metrics by department
                $sql = "SELECT 
                    e.department_name,
                    COUNT(pr.id) as total_reviews,
                    AVG(pr.overall_rating) as avg_rating,
                    COUNT(CASE WHEN pr.status = 'completed' THEN 1 END) as completed_reviews,
                    COUNT(CASE WHEN pr.overall_rating >= 4 THEN 1 END) as high_performers
                FROM hr_performance_reviews pr
                LEFT JOIN employees e ON pr.employee_id = e.employee_id
                WHERE pr.created_at >= DATE_SUB(NOW(), INTERVAL $period MONTH)
                $where_clause
                GROUP BY e.department_name
                ORDER BY avg_rating DESC";
                
                $result = $conn->query($sql);
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $data]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_trend_data':
            try {
                $period = $_POST['period'] ?? '12';
                $metric = $_POST['metric'] ?? 'rating';
                
                $trends = [];
                for ($i = intval($period) - 1; $i >= 0; $i--) {
                    $month = date('Y-m', strtotime("-$i months"));
                    $month_name = date('M Y', strtotime("-$i months"));
                    
                    $sql = "SELECT 
                        COUNT(pr.id) as reviews,
                        AVG(pr.overall_rating) as avg_rating,
                        COUNT(CASE WHEN pr.status = 'completed' THEN 1 END) as completed
                    FROM hr_performance_reviews pr
                    WHERE DATE_FORMAT(pr.created_at, '%Y-%m') = '$month'";
                    
                    $result = $conn->query($sql);
                    $row = $result->fetch_assoc();
                    
                    $trends[] = [
                        'month' => $month_name,
                        'reviews' => intval($row['reviews'] ?? 0),
                        'avg_rating' => floatval($row['avg_rating'] ?? 0),
                        'completed' => intval($row['completed'] ?? 0)
                    ];
                }
                
                echo json_encode(['success' => true, 'data' => $trends]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_employee_performance':
            try {
                $employee_id = $_POST['employee_id'] ?? 0;
                $period = $_POST['period'] ?? '12';
                
                // Get employee performance history
                $sql = "SELECT 
                    pr.*,
                    e.name as employee_name,
                    e.department_name,
                    e.position,
                    DATE_FORMAT(pr.created_at, '%M %Y') as review_period
                FROM hr_performance_reviews pr
                LEFT JOIN employees e ON pr.employee_id = e.employee_id
                WHERE pr.employee_id = ? 
                AND pr.created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                ORDER BY pr.created_at DESC";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $employee_id, $period);
                $stmt->execute();
                $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Get employee goals
                $sql = "SELECT * FROM employee_goals WHERE employee_id = ? ORDER BY created_at DESC LIMIT 10";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $employee_id);
                $stmt->execute();
                $goals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode(['success' => true, 'reviews' => $reviews, 'goals' => $goals]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'export_analytics_report':
            try {
                $format = $_POST['format'] ?? 'csv';
                $period = $_POST['period'] ?? '12';
                
                // Generate analytics report
                $sql = "SELECT 
                    e.name as employee_name,
                    e.department_name,
                    e.position,
                    AVG(pr.overall_rating) as avg_rating,
                    COUNT(pr.id) as total_reviews,
                    COUNT(CASE WHEN pr.status = 'completed' THEN 1 END) as completed_reviews,
                    MAX(pr.created_at) as last_review_date
                FROM employees e
                LEFT JOIN hr_performance_reviews pr ON e.employee_id = pr.employee_id 
                    AND pr.created_at >= DATE_SUB(NOW(), INTERVAL $period MONTH)
                WHERE e.status = 'active'
                GROUP BY e.employee_id
                ORDER BY avg_rating DESC";
                
                $result = $conn->query($sql);
                $data = $result->fetch_all(MYSQLI_ASSOC);
                
                if ($format === 'csv') {
                    $filename = 'performance_analytics_' . date('Y-m-d') . '.csv';
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    
                    $output = fopen('php://output', 'w');
                    fputcsv($output, ['Employee', 'Department', 'Position', 'Avg Rating', 'Total Reviews', 'Completed Reviews', 'Last Review']);
                    
                    foreach ($data as $row) {
                        fputcsv($output, $row);
                    }
                    fclose($output);
                    exit;
                }
                
                echo json_encode(['success' => true, 'data' => $data]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Fetch main analytics data
try {
    // Overall statistics
    $stats_sql = "SELECT 
        COUNT(DISTINCT pr.employee_id) as total_employees_reviewed,
        COUNT(pr.id) as total_reviews,
        AVG(pr.overall_rating) as avg_rating,
        COUNT(CASE WHEN pr.status = 'completed' THEN 1 END) as completed_reviews,
        COUNT(CASE WHEN pr.overall_rating >= 4 THEN 1 END) as high_performers
    FROM hr_performance_reviews pr
    WHERE pr.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
    
    $stats_result = $conn->query($stats_sql);
    $overall_stats = $stats_result->fetch_assoc();
    
    // Department performance
    $dept_sql = "SELECT 
        e.department_name,
        COUNT(pr.id) as reviews,
        AVG(pr.overall_rating) as avg_rating,
        COUNT(CASE WHEN pr.overall_rating >= 4 THEN 1 END) as high_performers
    FROM hr_performance_reviews pr
    LEFT JOIN employees e ON pr.employee_id = e.employee_id
    WHERE pr.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY e.department_name
    HAVING reviews > 0
    ORDER BY avg_rating DESC";
    
    $dept_result = $conn->query($dept_sql);
    $department_stats = [];
    while ($row = $dept_result->fetch_assoc()) {
        $department_stats[] = $row;
    }
    
    // Top performers
    $top_performers_sql = "SELECT 
        e.name as employee_name,
        e.department_name,
        e.position,
        AVG(pr.overall_rating) as avg_rating,
        COUNT(pr.id) as reviews
    FROM hr_performance_reviews pr
    LEFT JOIN employees e ON pr.employee_id = e.employee_id
    WHERE pr.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY pr.employee_id
    HAVING avg_rating >= 4 AND reviews >= 2
    ORDER BY avg_rating DESC, reviews DESC
    LIMIT 10";
    
    $top_result = $conn->query($top_performers_sql);
    $top_performers = [];
    while ($row = $top_result->fetch_assoc()) {
        $top_performers[] = $row;
    }
    
    // Goal completion rates
    $goals_sql = "SELECT 
        status,
        COUNT(*) as count
    FROM employee_goals 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY status";
    
    $goals_result = $conn->query($goals_sql);
    $goal_stats = [];
    while ($row = $goals_result->fetch_assoc()) {
        $goal_stats[$row['status']] = $row['count'];
    }
    
    // Recent activities
    $activities_sql = "SELECT 
        'review' as type,
        pr.id,
        e.name as employee_name,
        pr.overall_rating as rating,
        pr.created_at
    FROM hr_performance_reviews pr
    LEFT JOIN employees e ON pr.employee_id = e.employee_id
    WHERE pr.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    UNION ALL
    SELECT 
        'goal' as type,
        g.id,
        e.name as employee_name,
        g.progress_percentage as rating,
        g.updated_at as created_at
    FROM employee_goals g
    LEFT JOIN employees e ON g.employee_id = e.employee_id
    WHERE g.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY created_at DESC
    LIMIT 20";
    
    $activities_result = $conn->query($activities_sql);
    $recent_activities = [];
    while ($row = $activities_result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
    
    // Get employees for dropdowns
    $employees_sql = "SELECT employee_id as id, name, department_name FROM employees WHERE status = 'active' ORDER BY name";
    $employees_result = $conn->query($employees_sql);
    $employees = [];
    while ($row = $employees_result->fetch_assoc()) {
        $employees[] = $row;
    }
    
    // Get departments
    $dept_list_sql = "SELECT DISTINCT department_name FROM employees WHERE status = 'active' AND department_name IS NOT NULL ORDER BY department_name";
    $dept_list_result = $conn->query($dept_list_sql);
    $departments = [];
    while ($row = $dept_list_result->fetch_assoc()) {
        $departments[] = $row['department_name'];
    }
    
} catch (Exception $e) {
    error_log("Performance Analytics Error: " . $e->getMessage());
    // Set default values
    $overall_stats = ['total_employees_reviewed' => 0, 'total_reviews' => 0, 'avg_rating' => 0, 'completed_reviews' => 0, 'high_performers' => 0];
    $department_stats = [];
    $top_performers = [];
    $goal_stats = [];
    $recent_activities = [];
    $employees = [];
    $departments = [];
}

include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📊 Performance Analytics</h1>
                <p class="text-muted">Advanced insights and performance metrics dashboard</p>
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-success" onclick="exportReport('csv')">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
                </button>
                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#filtersModal">
                    <i class="bi bi-funnel me-1"></i>Filters
                </button>
                <button type="button" class="btn btn-warning" onclick="refreshData()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Key Metrics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-2-4 col-lg-4 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-people fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $overall_stats['total_employees_reviewed'] ?></h3>
                        <small class="opacity-75">Employees Reviewed</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2-4 col-lg-4 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-clipboard-check fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $overall_stats['total_reviews'] ?></h3>
                        <small class="opacity-75">Total Reviews</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2-4 col-lg-4 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-star-fill fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= number_format($overall_stats['avg_rating'], 1) ?></h3>
                        <small class="opacity-75">Average Rating</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2-4 col-lg-4 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-trophy fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold"><?= $overall_stats['high_performers'] ?></h3>
                        <small class="opacity-75">High Performers</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2-4 col-lg-4 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-circle fs-2" style="color: #28a745;"></i>
                        </div>
                        <h3 class="mb-1 fw-bold" style="color: #333;"><?= $overall_stats['completed_reviews'] ?></h3>
                        <small style="color: #666;">Completed Reviews</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Performance Trends</h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary active" onclick="updateTrendChart('6')">6M</button>
                            <button type="button" class="btn btn-outline-primary" onclick="updateTrendChart('12')">12M</button>
                            <button type="button" class="btn btn-outline-primary" onclick="updateTrendChart('24')">24M</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceTrendChart" height="100"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="bi bi-pie-chart me-2 text-success"></i>Goal Completion</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="goalCompletionChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Department Performance and Top Performers -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="bi bi-building me-2 text-info"></i>Department Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Department</th>
                                        <th>Reviews</th>
                                        <th>Avg Rating</th>
                                        <th>Top Performers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($department_stats as $dept): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($dept['department_name'] ?? 'Unknown') ?></strong></td>
                                        <td><span class="badge bg-primary"><?= $dept['reviews'] ?></span></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php
                                                $rating = floatval($dept['avg_rating']);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    if ($i <= $rating) {
                                                        echo '<i class="bi bi-star-fill text-warning me-1"></i>';
                                                    } else {
                                                        echo '<i class="bi bi-star text-muted me-1"></i>';
                                                    }
                                                }
                                                ?>
                                                <span class="ms-1"><?= number_format($rating, 1) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-success"><?= $dept['high_performers'] ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="bi bi-award me-2 text-warning"></i>Top Performers</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_performers)): ?>
                            <?php foreach ($top_performers as $index => $performer): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <?php if ($index < 3): ?>
                                        <span class="badge bg-<?= ['warning', 'secondary', 'dark'][$index] ?> fs-6">
                                            #<?= $index + 1 ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark fs-6">#<?= $index + 1 ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?= htmlspecialchars($performer['employee_name']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($performer['department_name'] ?? 'Unknown') ?> • <?= htmlspecialchars($performer['position'] ?? 'N/A') ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-success"><?= number_format($performer['avg_rating'], 1) ?>/5</div>
                                    <small class="text-muted"><?= $performer['reviews'] ?> reviews</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No performance data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2 text-secondary"></i>Recent Performance Activities</h5>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#employeeAnalyticsModal">
                            <i class="bi bi-person-check me-1"></i>Employee Analytics
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_activities)): ?>
                            <div class="row">
                                <?php foreach (array_slice($recent_activities, 0, 8) as $activity): ?>
                                <div class="col-lg-6 col-xl-4 mb-3">
                                    <div class="d-flex align-items-center p-3 bg-light rounded">
                                        <div class="me-3">
                                            <?php if ($activity['type'] === 'review'): ?>
                                                <i class="bi bi-clipboard-check text-primary fs-4"></i>
                                            <?php else: ?>
                                                <i class="bi bi-bullseye text-success fs-4"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= htmlspecialchars($activity['employee_name']) ?></h6>
                                            <small class="text-muted">
                                                <?php if ($activity['type'] === 'review'): ?>
                                                    Performance Review • Rating: <?= number_format($activity['rating'], 1) ?>/5
                                                <?php else: ?>
                                                    Goal Update • Progress: <?= number_format($activity['rating'], 0) ?>%
                                                <?php endif; ?>
                                            </small>
                                            <div class="text-muted small"><?= date('M d, Y', strtotime($activity['created_at'])) ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No recent activities</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Modal -->
<div class="modal fade" id="filtersModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Analytics Filters</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="filtersForm">
                    <div class="mb-3">
                        <label for="periodSelect" class="form-label">Time Period</label>
                        <select class="form-select" id="periodSelect" name="period">
                            <option value="3">Last 3 Months</option>
                            <option value="6">Last 6 Months</option>
                            <option value="12" selected>Last 12 Months</option>
                            <option value="24">Last 24 Months</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="departmentSelect" class="form-label">Department</label>
                        <select class="form-select" id="departmentSelect" name="department">
                            <option value="all">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- Employee Analytics Modal -->
<div class="modal fade" id="employeeAnalyticsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Employee Performance Analytics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="employeeAnalyticsForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="employeeSelect" class="form-label">Select Employee</label>
                                <select class="form-select" id="employeeSelect" name="employee_id" required>
                                    <option value="">Choose Employee...</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> - <?= htmlspecialchars($emp['department_name'] ?? 'N/A') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="employeePeriodSelect" class="form-label">Period</label>
                                <select class="form-select" id="employeePeriodSelect" name="period">
                                    <option value="6">Last 6 Months</option>
                                    <option value="12" selected>Last 12 Months</option>
                                    <option value="24">Last 24 Months</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div id="employeeAnalyticsResults" style="display: none;">
                        <div class="row">
                            <div class="col-12">
                                <h6>Performance History</h6>
                                <div id="employeePerformanceData"></div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="loadEmployeeAnalytics()">Generate Report</button>
            </div>
        </div>
    </div>
</div>

<script>
let performanceTrendChart, goalCompletionChart;

document.addEventListener('DOMContentLoaded', function() {
    initCharts();
});

function initCharts() {
    // Performance Trend Chart
    const trendCtx = document.getElementById('performanceTrendChart');
    if (trendCtx) {
        updateTrendChart('12');
    }
    
    // Goal Completion Chart
    const goalCtx = document.getElementById('goalCompletionChart');
    if (goalCtx) {
        const goalData = <?= json_encode($goal_stats) ?>;
        const labels = [];
        const data = [];
        const colors = ['#28a745', '#ffc107', '#17a2b8', '#dc3545', '#6c757d'];
        
        Object.keys(goalData).forEach(status => {
            labels.push(status.replace('_', ' ').toUpperCase());
            data.push(goalData[status]);
        });
        
        goalCompletionChart = new Chart(goalCtx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors.slice(0, labels.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

function updateTrendChart(period) {
    fetch('performance_analytics.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_trend_data&period=${period}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const ctx = document.getElementById('performanceTrendChart');
            
            if (performanceTrendChart) {
                performanceTrendChart.destroy();
            }
            
            performanceTrendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.data.map(item => item.month),
                    datasets: [
                        {
                            label: 'Average Rating',
                            data: data.data.map(item => item.avg_rating),
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Total Reviews',
                            data: data.data.map(item => item.reviews),
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Average Rating'
                            },
                            max: 5,
                            min: 0
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Number of Reviews'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
            
            // Update active button
            document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }
    })
    .catch(error => {
        console.error('Error updating trend chart:', error);
        showAlert('Error updating trend chart', 'error');
    });
}

function applyFilters() {
    const formData = new FormData(document.getElementById('filtersForm'));
    formData.append('action', 'get_department_analytics');
    
    fetch('performance_analytics.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update department table or reload page
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error applying filters:', error);
    });
    
    $('#filtersModal').modal('hide');
}

function loadEmployeeAnalytics() {
    const formData = new FormData(document.getElementById('employeeAnalyticsForm'));
    formData.append('action', 'get_employee_performance');
    
    fetch('performance_analytics.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayEmployeeAnalytics(data);
        } else {
            showAlert('Error loading employee analytics', 'error');
        }
    })
    .catch(error => {
        console.error('Error loading employee analytics:', error);
        showAlert('Error loading employee analytics', 'error');
    });
}

function displayEmployeeAnalytics(data) {
    const resultsDiv = document.getElementById('employeeAnalyticsResults');
    const dataDiv = document.getElementById('employeePerformanceData');
    
    let html = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Review Period</th><th>Rating</th><th>Status</th><th>Date</th></tr></thead><tbody>';
    
    data.reviews.forEach(review => {
        html += `<tr>
            <td>${review.review_period}</td>
            <td>${review.overall_rating}/5</td>
            <td><span class="badge bg-${review.status === 'completed' ? 'success' : 'warning'}">${review.status}</span></td>
            <td>${new Date(review.created_at).toLocaleDateString()}</td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    
    if (data.goals.length > 0) {
        html += '<h6 class="mt-3">Recent Goals</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Goal</th><th>Progress</th><th>Status</th></tr></thead><tbody>';
        
        data.goals.forEach(goal => {
            html += `<tr>
                <td>${goal.goal_title}</td>
                <td>${goal.progress_percentage}%</td>
                <td><span class="badge bg-${goal.status === 'completed' ? 'success' : 'primary'}">${goal.status}</span></td>
            </tr>`;
        });
        
        html += '</tbody></table></div>';
    }
    
    dataDiv.innerHTML = html;
    resultsDiv.style.display = 'block';
}

function exportReport(format) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'performance_analytics.php';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'export_analytics_report';
    form.appendChild(actionInput);
    
    const formatInput = document.createElement('input');
    formatInput.type = 'hidden';
    formatInput.name = 'format';
    formatInput.value = format;
    form.appendChild(formatInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function refreshData() {
    location.reload();
}

function showAlert(message, type = 'info') {
    Swal.fire({
        title: type === 'error' ? 'Error!' : 'Success!',
        text: message,
        icon: type,
        timer: 3000,
        showConfirmButton: false
    });
}
</script>

<style>
.col-xl-2-4 {
    flex: 0 0 20%;
    max-width: 20%;
}

@media (max-width: 1200px) {
    .col-xl-2-4 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }
}

@media (max-width: 768px) {
    .col-xl-2-4 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

.stats-card:hover {
    transform: translateY(-5px);
    transition: transform 0.3s ease;
}

.table th {
    border-top: none;
    font-weight: 600;
}

.card {
    border: none;
}

.card-header {
    font-weight: 600;
}
</style>

    </div>
</div>

<?php include '../layouts/footer.php'; ?>
