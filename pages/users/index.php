<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$page_title = 'User Management Hub';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="text-center mb-5">
                <h1 class="display-6 fw-bold text-primary mb-3">👥 User Management Hub</h1>
                <p class="lead text-muted">
                    Comprehensive user administration and monitoring tools
                </p>
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="alert alert-info border-0 bg-light-blue">
                            <i class="bi bi-info-circle"></i> 
                            <strong>Welcome!</strong> This is your centralized dashboard for all user-related operations. 
                            Choose from the management options below to get started.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Management Cards -->
            <div class="row g-4">
                <!-- User Management -->
                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm hover-card">
                        <div class="card-body text-center p-4">
                            <div class="mb-4">
                                <div class="bg-primary bg-gradient rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                    <i class="bi bi-people-fill text-white fs-1"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold mb-3">User Management</h4>
                            <p class="text-muted mb-4">
                                Create, edit, and manage user accounts. Handle user profiles, 
                                passwords, and account settings with comprehensive CRUD operations.
                            </p>
                            <div class="mb-3">
                                <small class="badge bg-primary me-1">CRUD Operations</small>
                                <small class="badge bg-primary me-1">Profile Management</small>
                                <small class="badge bg-primary">Bulk Actions</small>
                            </div>
                            <a href="user_management.php" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-arrow-right-circle"></i> Manage Users
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Roles & Permissions -->
                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm hover-card">
                        <div class="card-body text-center p-4">
                            <div class="mb-4">
                                <div class="bg-success bg-gradient rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                    <i class="bi bi-shield-lock text-white fs-1"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold mb-3">Roles & Permissions</h4>
                            <p class="text-muted mb-4">
                                Configure user roles and access permissions. Set up security 
                                policies and control what users can access in the system.
                            </p>
                            <div class="mb-3">
                                <small class="badge bg-success me-1">Role Management</small>
                                <small class="badge bg-success me-1">Permissions</small>
                                <small class="badge bg-success">Security</small>
                            </div>
                            <a href="roles_permissions.php" class="btn btn-success btn-lg w-100">
                                <i class="bi bi-key"></i> Manage Roles
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Activity Monitor -->
                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm hover-card">
                        <div class="card-body text-center p-4">
                            <div class="mb-4">
                                <div class="bg-info bg-gradient rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                    <i class="bi bi-activity text-white fs-1"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold mb-3">Activity Monitor</h4>
                            <p class="text-muted mb-4">
                                Track user activities, monitor login sessions, and view 
                                comprehensive analytics of system usage and user behavior.
                            </p>
                            <div class="mb-3">
                                <small class="badge bg-info me-1">Activity Tracking</small>
                                <small class="badge bg-info me-1">Analytics</small>
                                <small class="badge bg-info">Real-time</small>
                            </div>
                            <a href="activity_monitor.php" class="btn btn-info btn-lg w-100">
                                <i class="bi bi-eye"></i> View Activity
                            </a>
                        </div>
                    </div>
                </div>

                <!-- User Profile -->
                <div class="col-lg-6">
                    <div class="card h-100 border-0 shadow-sm hover-card">
                        <div class="card-body text-center p-4">
                            <div class="mb-4">
                                <div class="bg-warning bg-gradient rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                    <i class="bi bi-person-circle text-white fs-1"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold mb-3">User Profile</h4>
                            <p class="text-muted mb-4">
                                Manage individual user profiles including personal information, 
                                avatar uploads, password changes, and activity history.
                            </p>
                            <div class="mb-3">
                                <small class="badge bg-warning me-1">Profile Editing</small>
                                <small class="badge bg-warning me-1">Avatar Upload</small>
                                <small class="badge bg-warning">Security Settings</small>
                            </div>
                            <a href="profile.php" class="btn btn-warning btn-lg w-100">
                                <i class="bi bi-person-gear"></i> Edit Profile
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="col-lg-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h5 class="mb-0 fw-semibold">
                                <i class="bi bi-graph-up-arrow"></i> Quick Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 border-end">
                                    <div class="p-3">
                                        <h3 class="text-primary mb-1" id="totalUsers">-</h3>
                                        <small class="text-muted">Total Users</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3">
                                        <h3 class="text-success mb-1" id="activeUsers">-</h3>
                                        <small class="text-muted">Active Today</small>
                                    </div>
                                </div>
                                <div class="col-6 border-end border-top pt-3">
                                    <div class="p-3">
                                        <h3 class="text-info mb-1" id="totalRoles">-</h3>
                                        <small class="text-muted">User Roles</small>
                                    </div>
                                </div>
                                <div class="col-6 border-top pt-3">
                                    <div class="p-3">
                                        <h3 class="text-warning mb-1" id="activeSessions">-</h3>
                                        <small class="text-muted">Active Sessions</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-top">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> 
                                    Statistics updated in real-time
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Feed -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-semibold">
                                <i class="bi bi-clock-history"></i> Recent User Activity
                            </h5>
                            <a href="activity_monitor.php" class="btn btn-sm btn-outline-primary">
                                View All Activity
                            </a>
                        </div>
                        <div class="card-body">
                            <div id="recentActivityFeed">
                                <div class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
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

<style>
.hover-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.hover-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.bg-light-blue {
    background-color: #e3f2fd !important;
}

.btn-lg {
    border-radius: 8px;
}

.card {
    border-radius: 12px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadQuickStats();
    loadRecentActivity();
});

function loadQuickStats() {
    // Load basic statistics
    fetch('../../api/user_management_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_users_count'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('totalUsers').textContent = data.total || 0;
            document.getElementById('activeUsers').textContent = data.active_today || 0;
        }
    })
    .catch(error => console.error('Error loading user stats:', error));
    
    // Load roles count
    fetch('../users/roles_permissions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_roles'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('totalRoles').textContent = data.roles.length || 0;
        }
    })
    .catch(error => console.error('Error loading roles:', error));
    
    // Load active sessions count
    fetch('../users/activity_monitor.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_activity_stats'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('activeSessions').textContent = data.stats.active_sessions || 0;
        }
    })
    .catch(error => console.error('Error loading activity stats:', error));
}

function loadRecentActivity() {
    fetch('../users/activity_monitor.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_activity_logs&limit=5&page=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayRecentActivity(data.logs);
        }
    })
    .catch(error => {
        console.error('Error loading recent activity:', error);
        document.getElementById('recentActivityFeed').innerHTML = 
            '<p class="text-muted text-center">Unable to load recent activity</p>';
    });
}

function displayRecentActivity(logs) {
    const container = document.getElementById('recentActivityFeed');
    let html = '';
    
    if (logs.length === 0) {
        html = '<p class="text-muted text-center">No recent activity</p>';
    } else {
        logs.forEach(log => {
            const userName = log.full_name || log.username || 'Unknown User';
            const timeAgo = moment(log.created_at).fromNow();
            
            html += `
                <div class="d-flex align-items-start mb-3 pb-3 border-bottom">
                    <div class="me-3">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="bi bi-person text-white"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">${userName}</h6>
                                <p class="mb-1 text-muted">${log.activity}</p>
                                <small class="text-muted">${timeAgo} from ${log.ip_address}</small>
                            </div>
                            <small class="text-muted">${moment(log.created_at).format('MMM DD, HH:mm')}</small>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    container.innerHTML = html;
}

// Load moment.js for time formatting
if (typeof moment === 'undefined') {
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js';
    document.head.appendChild(script);
}
</script>

<?php include '../../layouts/footer.php'; ?>
