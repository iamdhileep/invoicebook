<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    // Check if admin login page exists, otherwise show auth message
    if (file_exists('../../admin/login.php')) {
        header("Location: ../../admin/login.php");
    } else {
        echo "<div class='container mt-5'>
                <div class='alert alert-warning'>
                    <h4>Authentication Required</h4>
                    <p>Please login to access the User Activity Monitor.</p>
                    <p><a href='../projects/quick_login.php?admin_id=1' class='btn btn-primary'>Quick Admin Login</a></p>
                </div>
              </div>";
    }
    exit;
}

include '../../db.php';

$page_title = 'User Activity Monitor';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_activity_logs':
            echo json_encode(getActivityLogs($conn, $_POST));
            exit;
            
        case 'get_login_sessions':
            echo json_encode(getLoginSessions($conn));
            exit;
            
        case 'terminate_session':
            echo json_encode(terminateSession($conn, $_POST['session_id']));
            exit;
            
        case 'get_activity_stats':
            echo json_encode(getActivityStats($conn));
            exit;
            
        case 'export_activity_log':
            exportActivityLog($conn, $_POST);
            exit;
            
        case 'clear_old_logs':
            echo json_encode(clearOldLogs($conn, $_POST['days']));
            exit;
    }
}

// Initialize activity monitoring tables
initializeActivityTables($conn);

function initializeActivityTables($conn) {
    // Ensure user_activity_log table exists with proper structure
    $createActivityTable = "CREATE TABLE IF NOT EXISTS user_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        activity VARCHAR(255) NOT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at),
        INDEX idx_activity (activity)
    )";
    
    if (!$conn->query($createActivityTable)) {
        error_log("Error creating activity log table: " . $conn->error);
    }
    
    // Ensure user_sessions table exists with proper structure
    $createSessionsTable = "CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_id VARCHAR(128) UNIQUE NOT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        logout_time TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_session_id (session_id),
        INDEX idx_is_active (is_active)
    )";
    
    if (!$conn->query($createSessionsTable)) {
        error_log("Error creating sessions table: " . $conn->error);
    }
}

function getActivityLogs($conn, $params) {
    $page = intval($params['page'] ?? 1);
    $limit = intval($params['limit'] ?? 50);
    $offset = ($page - 1) * $limit;
    
    $whereConditions = [];
    $bindings = [];
    $types = "";
    
    // Filter by user
    if (!empty($params['user_id'])) {
        $whereConditions[] = "a.user_id = ?";
        $bindings[] = $params['user_id'];
        $types .= "i";
    }
    
    // Filter by activity type
    if (!empty($params['activity_type'])) {
        $whereConditions[] = "a.activity LIKE ?";
        $bindings[] = "%" . $params['activity_type'] . "%";
        $types .= "s";
    }
    
    // Filter by date range
    if (!empty($params['date_from'])) {
        $whereConditions[] = "a.created_at >= ?";
        $bindings[] = $params['date_from'] . " 00:00:00";
        $types .= "s";
    }
    
    if (!empty($params['date_to'])) {
        $whereConditions[] = "a.created_at <= ?";
        $bindings[] = $params['date_to'] . " 23:59:59";
        $types .= "s";
    }
    
    // Filter by IP address
    if (!empty($params['ip_address'])) {
        $whereConditions[] = "a.ip_address = ?";
        $bindings[] = $params['ip_address'];
        $types .= "s";
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total 
                   FROM user_activity_log a 
                   LEFT JOIN users u ON a.user_id = u.id 
                   $whereClause";
    
    $totalCount = 0;
    if (!empty($bindings)) {
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bind_param($types, ...$bindings);
        $countStmt->execute();
        $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
    } else {
        $totalCount = $conn->query($countQuery)->fetch_assoc()['total'];
    }
    
    // Get activity logs
    $query = "SELECT a.*, u.username, u.email, u.full_name
              FROM user_activity_log a
              LEFT JOIN users u ON a.user_id = u.id
              $whereClause
              ORDER BY a.created_at DESC
              LIMIT $limit OFFSET $offset";
    
    $logs = [];
    if (!empty($bindings)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$bindings);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
    }
    
    return [
        'success' => true, 
        'logs' => $logs,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalCount / $limit),
            'total_records' => $totalCount,
            'per_page' => $limit
        ]
    ];
}

function getLoginSessions($conn) {
    $query = "SELECT s.*, u.username, u.email, u.full_name
              FROM user_sessions s
              LEFT JOIN users u ON s.user_id = u.id
              WHERE s.is_active = TRUE
              ORDER BY s.last_activity DESC";
    
    $result = $conn->query($query);
    $sessions = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $sessions[] = $row;
        }
    }
    
    return ['success' => true, 'sessions' => $sessions];
}

function terminateSession($conn, $sessionId) {
    $query = "UPDATE user_sessions SET is_active = FALSE, logout_time = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $sessionId);
    
    if ($stmt->execute()) {
        logActivity($conn, "Terminated session: $sessionId", "Session termination by admin");
        return ['success' => true, 'message' => 'Session terminated successfully'];
    } else {
        return ['success' => false, 'message' => 'Error terminating session'];
    }
}

function getActivityStats($conn) {
    $stats = [];
    
    // Activities in last 24 hours
    $stats['last_24h'] = $conn->query("SELECT COUNT(*) as count FROM user_activity_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['count'];
    
    // Activities in last 7 days
    $stats['last_7d'] = $conn->query("SELECT COUNT(*) as count FROM user_activity_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['count'];
    
    // Active sessions
    $stats['active_sessions'] = $conn->query("SELECT COUNT(*) as count FROM user_sessions WHERE is_active = TRUE")->fetch_assoc()['count'];
    
    // Total users
    $stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
    
    // Top activities today
    $topActivitiesQuery = "SELECT activity, COUNT(*) as count 
                          FROM user_activity_log 
                          WHERE DATE(created_at) = CURDATE() 
                          GROUP BY activity 
                          ORDER BY count DESC 
                          LIMIT 5";
    $result = $conn->query($topActivitiesQuery);
    $topActivities = [];
    while ($row = $result->fetch_assoc()) {
        $topActivities[] = $row;
    }
    $stats['top_activities'] = $topActivities;
    
    // Most active users today
    $activeUsersQuery = "SELECT u.username, u.full_name, COUNT(a.id) as activity_count
                        FROM user_activity_log a
                        LEFT JOIN users u ON a.user_id = u.id
                        WHERE DATE(a.created_at) = CURDATE()
                        GROUP BY a.user_id, u.username, u.full_name
                        ORDER BY activity_count DESC
                        LIMIT 5";
    $result = $conn->query($activeUsersQuery);
    $activeUsers = [];
    while ($row = $result->fetch_assoc()) {
        $activeUsers[] = $row;
    }
    $stats['active_users'] = $activeUsers;
    
    // Activity by hour (last 24 hours)
    $hourlyQuery = "SELECT HOUR(created_at) as hour, COUNT(*) as count
                   FROM user_activity_log
                   WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                   GROUP BY HOUR(created_at)
                   ORDER BY hour";
    $result = $conn->query($hourlyQuery);
    $hourlyActivity = [];
    for ($i = 0; $i < 24; $i++) {
        $hourlyActivity[$i] = 0;
    }
    while ($row = $result->fetch_assoc()) {
        $hourlyActivity[$row['hour']] = $row['count'];
    }
    $stats['hourly_activity'] = array_values($hourlyActivity);
    
    return ['success' => true, 'stats' => $stats];
}

function clearOldLogs($conn, $days) {
    $days = intval($days);
    if ($days < 1) $days = 30;
    
    $query = "DELETE FROM user_activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $days);
    
    if ($stmt->execute()) {
        $deleted = $stmt->affected_rows;
        logActivity($conn, "Cleared old activity logs", "Deleted $deleted records older than $days days");
        return ['success' => true, 'message' => "Deleted $deleted old activity records"];
    } else {
        return ['success' => false, 'message' => 'Error clearing old logs'];
    }
}

function exportActivityLog($conn, $params) {
    // Get activity logs based on filters
    $logs = getActivityLogs($conn, $params);
    
    if (!$logs['success']) {
        return ['success' => false, 'message' => 'Error retrieving activity logs'];
    }
    
    // Generate CSV content
    $filename = 'activity_log_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'ID', 'User ID', 'Username', 'Full Name', 'Email', 
        'Activity', 'Details', 'IP Address', 'User Agent', 'Timestamp'
    ]);
    
    // CSV data rows
    foreach ($logs['logs'] as $log) {
        fputcsv($output, [
            $log['id'],
            $log['user_id'],
            $log['username'] ?? 'Unknown',
            $log['full_name'] ?? 'N/A',
            $log['email'] ?? 'N/A',
            $log['activity'],
            $log['details'] ?? '',
            $log['ip_address'] ?? 'Unknown',
            $log['user_agent'] ?? 'Unknown',
            $log['created_at']
        ]);
    }
    
    fclose($output);
    exit; // Important: exit to prevent additional output
}

function logActivity($conn, $activity, $details = null) {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $query = "INSERT INTO user_activity_log (user_id, activity, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt->bind_param("issss", $userId, $activity, $details, $ipAddress, $userAgent);
        $stmt->execute();
    }
}

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-1 fw-bold text-primary">👁️ Activity Monitor</h1>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-activity"></i> 
                        Monitor user activities and system usage
                        <span class="badge bg-light text-dark ms-2">Real-time Monitoring</span>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success btn-sm" onclick="exportActivityLog()">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-outline-warning btn-sm" onclick="showClearLogsModal()">
                        <i class="bi bi-trash"></i> Clean Logs
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshData()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Last 24 Hours</h6>
                                    <h4 class="mb-0" id="stats24h">-</h4>
                                </div>
                                <i class="bi bi-clock-history fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Last 7 Days</h6>
                                    <h4 class="mb-0" id="stats7d">-</h4>
                                </div>
                                <i class="bi bi-calendar-week fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Active Sessions</h6>
                                    <h4 class="mb-0" id="activeSessions">-</h4>
                                </div>
                                <i class="bi bi-people-fill fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-warning text-dark">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Total Users</h6>
                                    <h4 class="mb-0" id="totalUsers">-</h4>
                                </div>
                                <i class="bi bi-person-lines-fill fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Activity Chart -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h6 class="mb-0 fw-semibold">
                                <i class="bi bi-graph-up"></i> Activity Chart (Last 24 Hours)
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="activityChart" height="100"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top Activities -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h6 class="mb-0 fw-semibold">
                                <i class="bi bi-list-ol"></i> Top Activities Today
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="topActivitiesList">
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Activity Logs -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-semibold">
                                <i class="bi bi-journal-text"></i> Activity Logs
                            </h6>
                            <div class="d-flex gap-2">
                                <select class="form-select form-select-sm" id="activityFilter" onchange="filterActivities()">
                                    <option value="">All Activities</option>
                                    <option value="login">Login</option>
                                    <option value="logout">Logout</option>
                                    <option value="create">Create</option>
                                    <option value="update">Update</option>
                                    <option value="delete">Delete</option>
                                </select>
                                <input type="date" class="form-control form-control-sm" id="dateFilter" onchange="filterActivities()">
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>User</th>
                                            <th>Activity</th>
                                            <th>IP Address</th>
                                            <th>Time</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody id="activityLogsTable">
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status"></div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-light border-0">
                            <nav>
                                <ul class="pagination pagination-sm mb-0" id="activityPagination">
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>

                <!-- Active Sessions & Most Active Users -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-light border-0">
                            <h6 class="mb-0 fw-semibold">
                                <i class="bi bi-shield-check"></i> Active Sessions
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <tbody id="activeSessionsTable">
                                        <tr>
                                            <td class="text-center py-3">
                                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h6 class="mb-0 fw-semibold">
                                <i class="bi bi-person-fill-up"></i> Most Active Users
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="activeUsersList">
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Clear Logs Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle"></i> Clear Old Activity Logs
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>This will permanently delete old activity log records. This action cannot be undone.</p>
                <div class="mb-3">
                    <label class="form-label">Delete logs older than:</label>
                    <select class="form-select" id="clearLogsDays">
                        <option value="30">30 days</option>
                        <option value="60">60 days</option>
                        <option value="90" selected>90 days</option>
                        <option value="180">6 months</option>
                        <option value="365">1 year</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="clearOldLogs()">
                    <i class="bi bi-trash"></i> Clear Logs
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let activityChart;
let currentPage = 1;
let currentFilters = {};

document.addEventListener('DOMContentLoaded', function() {
    loadActivityStats();
    loadActivityLogs();
    loadActiveSessions();
});

function loadActivityStats() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_activity_stats'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayStats(data.stats);
            displayTopActivities(data.stats.top_activities);
            displayActiveUsers(data.stats.active_users);
            displayActivityChart(data.stats.hourly_activity);
        }
    })
    .catch(error => console.error('Error loading stats:', error));
}

function displayStats(stats) {
    document.getElementById('stats24h').textContent = stats.last_24h || 0;
    document.getElementById('stats7d').textContent = stats.last_7d || 0;
    document.getElementById('activeSessions').textContent = stats.active_sessions || 0;
    document.getElementById('totalUsers').textContent = stats.total_users || 0;
}

function displayTopActivities(activities) {
    const container = document.getElementById('topActivitiesList');
    let html = '';
    
    if (activities.length === 0) {
        html = '<p class="text-muted text-center">No activities today</p>';
    } else {
        activities.forEach(activity => {
            html += `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold">${activity.activity}</span>
                    <span class="badge bg-primary">${activity.count}</span>
                </div>
            `;
        });
    }
    
    container.innerHTML = html;
}

function displayActiveUsers(users) {
    const container = document.getElementById('activeUsersList');
    let html = '';
    
    if (users.length === 0) {
        html = '<p class="text-muted text-center">No active users today</p>';
    } else {
        users.forEach(user => {
            html += `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="fw-semibold">${user.full_name || user.username}</div>
                        <small class="text-muted">@${user.username}</small>
                    </div>
                    <span class="badge bg-success">${user.activity_count}</span>
                </div>
            `;
        });
    }
    
    container.innerHTML = html;
}

function displayActivityChart(hourlyData) {
    const ctx = document.getElementById('activityChart').getContext('2d');
    
    if (activityChart) {
        activityChart.destroy();
    }
    
    const labels = [];
    for (let i = 0; i < 24; i++) {
        labels.push(i + ':00');
    }
    
    activityChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Activities',
                data: hourlyData,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function loadActivityLogs(page = 1, filters = {}) {
    currentPage = page;
    currentFilters = filters;
    
    const formData = new FormData();
    formData.append('action', 'get_activity_logs');
    formData.append('page', page);
    formData.append('limit', 20);
    
    Object.keys(filters).forEach(key => {
        if (filters[key]) {
            formData.append(key, filters[key]);
        }
    });
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayActivityLogs(data.logs);
            displayPagination(data.pagination);
        }
    })
    .catch(error => console.error('Error loading logs:', error));
}

function displayActivityLogs(logs) {
    const tbody = document.getElementById('activityLogsTable');
    let html = '';
    
    if (logs.length === 0) {
        html = '<tr><td colspan="5" class="text-center py-4">No activity logs found</td></tr>';
    } else {
        logs.forEach(log => {
            const userName = log.full_name || log.username || 'Unknown User';
            const activityBadge = getActivityBadge(log.activity);
            const timeAgo = moment(log.created_at).fromNow();
            
            html += `
                <tr>
                    <td>
                        <div class="fw-semibold">${userName}</div>
                        <small class="text-muted">${log.email || ''}</small>
                    </td>
                    <td>
                        ${activityBadge}
                        <small class="d-block text-muted">${log.activity}</small>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark">${log.ip_address || 'Unknown'}</span>
                    </td>
                    <td>
                        <span class="fw-semibold">${moment(log.created_at).format('MMM DD, HH:mm')}</span>
                        <small class="d-block text-muted">${timeAgo}</small>
                    </td>
                    <td>
                        ${log.details ? `<small class="text-muted">${log.details}</small>` : '-'}
                    </td>
                </tr>
            `;
        });
    }
    
    tbody.innerHTML = html;
}

function displayPagination(pagination) {
    const container = document.getElementById('activityPagination');
    let html = '';
    
    if (pagination.total_pages > 1) {
        // Previous button
        const prevDisabled = pagination.current_page === 1 ? 'disabled' : '';
        html += `
            <li class="page-item ${prevDisabled}">
                <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1})">Previous</a>
            </li>
        `;
        
        // Page numbers
        const start = Math.max(1, pagination.current_page - 2);
        const end = Math.min(pagination.total_pages, pagination.current_page + 2);
        
        for (let i = start; i <= end; i++) {
            const active = i === pagination.current_page ? 'active' : '';
            html += `
                <li class="page-item ${active}">
                    <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
                </li>
            `;
        }
        
        // Next button
        const nextDisabled = pagination.current_page === pagination.total_pages ? 'disabled' : '';
        html += `
            <li class="page-item ${nextDisabled}">
                <a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1})">Next</a>
            </li>
        `;
    }
    
    container.innerHTML = html;
}

function getActivityBadge(activity) {
    if (activity.toLowerCase().includes('login')) return '<span class="badge bg-success">Login</span>';
    if (activity.toLowerCase().includes('logout')) return '<span class="badge bg-warning">Logout</span>';
    if (activity.toLowerCase().includes('create')) return '<span class="badge bg-primary">Create</span>';
    if (activity.toLowerCase().includes('update')) return '<span class="badge bg-info">Update</span>';
    if (activity.toLowerCase().includes('delete')) return '<span class="badge bg-danger">Delete</span>';
    return '<span class="badge bg-secondary">Activity</span>';
}

function loadActiveSessions() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_login_sessions'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayActiveSessions(data.sessions);
        }
    })
    .catch(error => console.error('Error loading sessions:', error));
}

function displayActiveSessions(sessions) {
    const tbody = document.getElementById('activeSessionsTable');
    let html = '';
    
    if (sessions.length === 0) {
        html = '<tr><td class="text-center py-3">No active sessions</td></tr>';
    } else {
        sessions.slice(0, 5).forEach(session => {
            const userName = session.full_name || session.username || 'Unknown';
            const lastActivity = moment(session.last_activity).fromNow();
            
            html += `
                <tr>
                    <td>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">${userName}</div>
                                <small class="text-muted">${session.ip_address}</small>
                                <small class="d-block text-muted">${lastActivity}</small>
                            </div>
                            <button class="btn btn-outline-danger btn-sm" onclick="terminateSession(${session.id})" title="Terminate Session">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
    }
    
    tbody.innerHTML = html;
}

function filterActivities() {
    const filters = {
        activity_type: document.getElementById('activityFilter').value,
        date_from: document.getElementById('dateFilter').value,
        date_to: document.getElementById('dateFilter').value
    };
    
    loadActivityLogs(1, filters);
}

function changePage(page) {
    if (page < 1) return;
    loadActivityLogs(page, currentFilters);
}

function terminateSession(sessionId) {
    if (confirm('Are you sure you want to terminate this session?')) {
        const formData = new FormData();
        formData.append('action', 'terminate_session');
        formData.append('session_id', sessionId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                loadActiveSessions();
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Network error terminating session');
        });
    }
}

function showClearLogsModal() {
    new bootstrap.Modal(document.getElementById('clearLogsModal')).show();
}

function clearOldLogs() {
    const days = document.getElementById('clearLogsDays').value;
    
    const formData = new FormData();
    formData.append('action', 'clear_old_logs');
    formData.append('days', days);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('clearLogsModal')).hide();
        if (data.success) {
            showAlert('success', data.message);
            loadActivityStats();
            loadActivityLogs();
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Network error clearing logs');
    });
}

function exportActivityLog() {
    showAlert('info', 'Export feature will be implemented with CSV/PDF options');
}

function refreshData() {
    loadActivityStats();
    loadActivityLogs(currentPage, currentFilters);
    loadActiveSessions();
    showAlert('success', 'Data refreshed successfully');
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv && alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Load moment.js for time formatting
if (typeof moment === 'undefined') {
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js';
    document.head.appendChild(script);
}
</script>

<?php include '../../layouts/footer.php'; ?>
