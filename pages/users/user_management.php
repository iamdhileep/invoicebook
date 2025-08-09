<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Include database connection
include '../../db.php';

$page_title = 'User Management';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_users':
            echo json_encode(getAllUsers($conn));
            exit;
            
        case 'add_user':
            $result = addUser($conn, $_POST);
            echo json_encode($result);
            exit;
            
        case 'update_user':
            $result = updateUser($conn, $_POST);
            echo json_encode($result);
            exit;
            
        case 'delete_user':
            $result = deleteUser($conn, $_POST['user_id']);
            echo json_encode($result);
            exit;
            
        case 'toggle_status':
            $result = toggleUserStatus($conn, $_POST['user_id']);
            echo json_encode($result);
            exit;
            
        case 'reset_password':
            $result = resetUserPassword($conn, $_POST['user_id']);
            echo json_encode($result);
            exit;
            
        case 'update_permissions':
            $result = updateUserPermissions($conn, $_POST['user_id'], $_POST['permissions']);
            echo json_encode($result);
            exit;
            
        case 'get_user_details':
            $result = getUserDetails($conn, $_POST['user_id']);
            echo json_encode($result);
            exit;
    }
}

// Initialize database tables
initializeUserTables($conn);

// User management functions
function initializeUserTables($conn) {
    // Create users table if not exists
    $createUsersTable = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('admin', 'manager', 'employee', 'user') DEFAULT 'user',
        status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
        permissions TEXT DEFAULT NULL,
        last_login DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by INT NULL,
        phone VARCHAR(20) NULL,
        address TEXT NULL,
        department VARCHAR(50) NULL,
        position VARCHAR(50) NULL,
        hire_date DATE NULL,
        avatar VARCHAR(255) NULL
    )";
    
    if (!$conn->query($createUsersTable)) {
        error_log("Error creating users table: " . $conn->error);
    }
    
    // Create user_sessions table for session tracking
    $createSessionsTable = "CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_token VARCHAR(255) NOT NULL,
        login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        logout_time TIMESTAMP NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        status ENUM('active', 'expired', 'logged_out') DEFAULT 'active',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if (!$conn->query($createSessionsTable)) {
        error_log("Error creating sessions table: " . $conn->error);
    }
    
    // Create user_activity_log table
    $createActivityTable = "CREATE TABLE IF NOT EXISTS user_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if (!$conn->query($createActivityTable)) {
        error_log("Error creating activity log table: " . $conn->error);
    }
}

function getAllUsers($conn) {
    $query = "SELECT 
        u.*,
        COALESCE(creator.username, 'System') as created_by_name,
        (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.id AND status = 'active') as active_sessions
        FROM users u
        LEFT JOIN users creator ON u.created_by = creator.id
        ORDER BY u.created_at DESC";
    
    $result = $conn->query($query);
    $users = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Don't return password in the response
            unset($row['password']);
            $users[] = $row;
        }
    }
    
    return ['success' => true, 'users' => $users];
}

function addUser($conn, $data) {
    // Validate required fields
    $required_fields = ['username', 'email', 'full_name', 'password', 'role'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Field $field is required"];
        }
    }
    
    // Check if username or email already exists
    $checkQuery = "SELECT id FROM users WHERE username = ? OR email = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ss", $data['username'], $data['email']);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Prepare insert query
    $query = "INSERT INTO users (username, email, password, full_name, role, status, phone, address, department, position, hire_date, created_by, permissions) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
    
    $created_by = $_SESSION['admin'] ?? $_SESSION['user_id'] ?? null;
    $status = $data['status'] ?? 'active';
    $permissions = isset($data['permissions']) ? json_encode($data['permissions']) : null;
    
    $stmt->bind_param("sssssssssssss", 
        $data['username'],
        $data['email'], 
        $hashedPassword,
        $data['full_name'],
        $data['role'],
        $status,
        $data['phone'] ?? null,
        $data['address'] ?? null,
        $data['department'] ?? null,
        $data['position'] ?? null,
        $data['hire_date'] ?? null,
        $created_by,
        $permissions
    );
    
    if ($stmt->execute()) {
        $userId = $conn->insert_id;
        
        // Log activity
        logUserActivity($conn, $created_by, 'user_created', "Created user: {$data['username']}");
        
        return ['success' => true, 'message' => 'User created successfully', 'user_id' => $userId];
    } else {
        return ['success' => false, 'message' => 'Error creating user: ' . $stmt->error];
    }
}

function updateUser($conn, $data) {
    if (empty($data['user_id'])) {
        return ['success' => false, 'message' => 'User ID is required'];
    }
    
    // Build dynamic update query
    $updates = [];
    $params = [];
    $types = "";
    
    $allowed_fields = ['username', 'email', 'full_name', 'role', 'status', 'phone', 'address', 'department', 'position', 'hire_date'];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field]) && $data[$field] !== '') {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
            $types .= "s";
        }
    }
    
    if (isset($data['permissions'])) {
        $updates[] = "permissions = ?";
        $params[] = json_encode($data['permissions']);
        $types .= "s";
    }
    
    if (empty($updates)) {
        return ['success' => false, 'message' => 'No fields to update'];
    }
    
    // Add user_id to params
    $params[] = $data['user_id'];
    $types .= "i";
    
    $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
    
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        // Log activity
        logUserActivity($conn, $_SESSION['admin'] ?? $_SESSION['user_id'], 'user_updated', "Updated user ID: {$data['user_id']}");
        
        return ['success' => true, 'message' => 'User updated successfully'];
    } else {
        return ['success' => false, 'message' => 'Error updating user: ' . $stmt->error];
    }
}

function deleteUser($conn, $userId) {
    if (empty($userId)) {
        return ['success' => false, 'message' => 'User ID is required'];
    }
    
    // Prevent self-deletion
    if ($userId == ($_SESSION['admin'] ?? $_SESSION['user_id'])) {
        return ['success' => false, 'message' => 'You cannot delete your own account'];
    }
    
    // Get user info before deletion
    $userQuery = "SELECT username FROM users WHERE id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    // Delete user (cascading will handle sessions and activity logs)
    $deleteQuery = "DELETE FROM users WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("i", $userId);
    
    if ($deleteStmt->execute()) {
        // Log activity
        logUserActivity($conn, $_SESSION['admin'] ?? $_SESSION['user_id'], 'user_deleted', "Deleted user: {$user['username']}");
        
        return ['success' => true, 'message' => 'User deleted successfully'];
    } else {
        return ['success' => false, 'message' => 'Error deleting user: ' . $deleteStmt->error];
    }
}

function toggleUserStatus($conn, $userId) {
    if (empty($userId)) {
        return ['success' => false, 'message' => 'User ID is required'];
    }
    
    // Get current status
    $statusQuery = "SELECT status, username FROM users WHERE id = ?";
    $statusStmt = $conn->prepare($statusQuery);
    $statusStmt->bind_param("i", $userId);
    $statusStmt->execute();
    $result = $statusStmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
    
    $updateQuery = "UPDATE users SET status = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("si", $newStatus, $userId);
    
    if ($updateStmt->execute()) {
        // Log activity
        logUserActivity($conn, $_SESSION['admin'] ?? $_SESSION['user_id'], 'status_changed', "Changed status of {$user['username']} to $newStatus");
        
        return ['success' => true, 'message' => "User status changed to $newStatus", 'new_status' => $newStatus];
    } else {
        return ['success' => false, 'message' => 'Error updating status: ' . $updateStmt->error];
    }
}

function resetUserPassword($conn, $userId) {
    if (empty($userId)) {
        return ['success' => false, 'message' => 'User ID is required'];
    }
    
    // Generate new password
    $newPassword = generateRandomPassword();
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $updateQuery = "UPDATE users SET password = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("si", $hashedPassword, $userId);
    
    if ($updateStmt->execute()) {
        // Get username for logging
        $userQuery = "SELECT username FROM users WHERE id = ?";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $user = $userResult->fetch_assoc();
        
        // Log activity
        logUserActivity($conn, $_SESSION['admin'] ?? $_SESSION['user_id'], 'password_reset', "Reset password for user: {$user['username']}");
        
        return ['success' => true, 'message' => 'Password reset successfully', 'new_password' => $newPassword];
    } else {
        return ['success' => false, 'message' => 'Error resetting password: ' . $updateStmt->error];
    }
}

function updateUserPermissions($conn, $userId, $permissions) {
    if (empty($userId)) {
        return ['success' => false, 'message' => 'User ID is required'];
    }
    
    $permissionsJson = is_array($permissions) ? json_encode($permissions) : $permissions;
    
    $updateQuery = "UPDATE users SET permissions = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("si", $permissionsJson, $userId);
    
    if ($updateStmt->execute()) {
        // Log activity
        logUserActivity($conn, $_SESSION['admin'] ?? $_SESSION['user_id'], 'permissions_updated', "Updated permissions for user ID: $userId");
        
        return ['success' => true, 'message' => 'Permissions updated successfully'];
    } else {
        return ['success' => false, 'message' => 'Error updating permissions: ' . $updateStmt->error];
    }
}

function getUserDetails($conn, $userId) {
    $query = "SELECT u.*, 
              (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.id) as total_sessions,
              (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.id AND status = 'active') as active_sessions,
              (SELECT login_time FROM user_sessions WHERE user_id = u.id ORDER BY login_time DESC LIMIT 1) as last_login_time
              FROM users u WHERE u.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Remove password from response
        unset($user['password']);
        
        // Get recent activity
        $activityQuery = "SELECT * FROM user_activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
        $activityStmt = $conn->prepare($activityQuery);
        $activityStmt->bind_param("i", $userId);
        $activityStmt->execute();
        $activityResult = $activityStmt->get_result();
        
        $activities = [];
        while ($activity = $activityResult->fetch_assoc()) {
            $activities[] = $activity;
        }
        
        $user['recent_activities'] = $activities;
        
        return ['success' => true, 'user' => $user];
    } else {
        return ['success' => false, 'message' => 'User not found'];
    }
}

function logUserActivity($conn, $userId, $action, $description) {
    if (!$userId) return;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $query = "INSERT INTO user_activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isss", $userId, $action, $description, $ip);
    $stmt->execute();
}

function generateRandomPassword($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">👥 User Management</h1>
                <p class="text-muted">Manage system users, roles, and permissions</p>
            </div>
            <div>
                <button class="btn btn-success me-2" onclick="exportUsers()">
                    <i class="bi bi-download"></i> Export Users
                </button>
                <button class="btn btn-primary" onclick="showAddUserModal()">
                    <i class="bi bi-person-plus"></i> Add User
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-people fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold" id="totalUsers">0</h3>
                        <small class="opacity-75">Total Users</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-person-check fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold" id="activeUsers">0</h3>
                        <small class="opacity-75">Active Users</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-shield-check fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold" id="adminUsers">0</h3>
                        <small class="opacity-75">Administrators</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-circle-fill fs-2"></i>
                        </div>
                        <h3 class="mb-1 fw-bold" id="onlineUsers">0</h3>
                        <small class="opacity-75">Online Now</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <button class="btn btn-outline-primary w-100" onclick="refreshUserList()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh Users
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-success w-100" onclick="exportUsers()">
                    <i class="bi bi-download me-2"></i>Export Data
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-info w-100" onclick="showUserReport()">
                    <i class="bi bi-bar-chart me-2"></i>User Analytics
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-warning w-100" onclick="managePermissions()">
                    <i class="bi bi-key me-2"></i>Manage Permissions
                </button>
            </div>
        </div>

        <!-- User Management Overview -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="bi bi-info-circle text-primary me-2"></i>
                                User Management Overview
                            </h6>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#userOverview">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </div>
                    </div>
                    <div class="collapse show" id="userOverview">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <small class="text-muted">System Status</small>
                                    <div class="h5 mb-0 text-success">
                                        <i class="bi bi-check-circle me-1"></i>Operational
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Last User Activity</small>
                                    <div class="h5 mb-0"><?= date('Y-m-d H:i') ?></div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">User Roles</small>
                                    <div class="h5 mb-0">4 Types</div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Quick Actions</small>
                                    <div class="mt-1">
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="loadUsers()">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success" onclick="showAlert('success', 'User system is running smoothly!')">
                                            <i class="bi bi-heart-pulse"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0">
                            <i class="bi bi-people me-2"></i>Users Management
                        </h5>
                        <small class="text-muted">Manage all system users and their permissions</small>
                    </div>
                    <div class="col-auto">
                        <div class="input-group">
                            <input type="text" class="form-control" id="userSearch" placeholder="Search users...">
                            <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="usersTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Avatar</th>
                                    <th>User Info</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <br>Loading users...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus"></i> Add New User
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Administrator</option>
                                <option value="manager">Manager</option>
                                <option value="employee">Employee</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="department">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Position</label>
                            <input type="text" class="form-control" name="position">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Hire Date</label>
                            <input type="date" class="form-control" name="hire_date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="addUser()">
                    <i class="bi bi-person-plus"></i> Add User
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-gear"></i> Edit User
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" name="user_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="admin">Administrator</option>
                                <option value="manager">Manager</option>
                                <option value="employee">Employee</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="department">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Position</label>
                            <input type="text" class="form-control" name="position">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Hire Date</label>
                            <input type="date" class="form-control" name="hire_date">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateUser()">
                    <i class="bi bi-check-lg"></i> Update User
                </button>
            </div>
        </div>
    </div>
</div>

<!-- User Details Modal -->
<div class="modal fade" id="userDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-lines-fill"></i> User Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Permissions Modal -->
<div class="modal fade" id="permissionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-key"></i> Manage Permissions
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="permissionsForm">
                    <input type="hidden" name="user_id">
                    <div class="mb-3">
                        <label class="form-label">Select Permissions</label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="dashboard" id="perm_dashboard">
                                    <label class="form-check-label" for="perm_dashboard">Dashboard Access</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="users" id="perm_users">
                                    <label class="form-check-label" for="perm_users">User Management</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="employees" id="perm_employees">
                                    <label class="form-check-label" for="perm_employees">Employee Management</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="attendance" id="perm_attendance">
                                    <label class="form-check-label" for="perm_attendance">Attendance</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="payroll" id="perm_payroll">
                                    <label class="form-check-label" for="perm_payroll">Payroll</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="reports" id="perm_reports">
                                    <label class="form-check-label" for="perm_reports">Reports</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="settings" id="perm_settings">
                                    <label class="form-check-label" for="perm_settings">System Settings</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="analytics" id="perm_analytics">
                                    <label class="form-check-label" for="perm_analytics">Analytics</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="updatePermissions()">
                    <i class="bi bi-key"></i> Update Permissions
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let usersData = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    
    // Search functionality
    document.getElementById('userSearch').addEventListener('keyup', filterUsers);
    document.getElementById('searchBtn').addEventListener('click', filterUsers);
});

// Load all users
function loadUsers() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_users'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            usersData = data.users;
            displayUsers(usersData);
            updateStatistics(usersData);
        } else {
            showAlert('danger', 'Error loading users');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Network error loading users');
    });
}

// Display users in table
function displayUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    let html = '';
    
    if (users.length === 0) {
        html = '<tr><td colspan="7" class="text-center py-4">No users found</td></tr>';
    } else {
        users.forEach(user => {
            const avatar = user.avatar ? `<img src="${user.avatar}" class="rounded-circle" width="40" height="40">` : 
                          `<div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 40px; height: 40px;">${user.full_name.charAt(0)}</div>`;
            
            const statusBadge = getStatusBadge(user.status);
            const roleBadge = getRoleBadge(user.role);
            const lastLogin = user.last_login ? formatDateTime(user.last_login) : 'Never';
            const created = formatDateTime(user.created_at);
            
            html += `
                <tr>
                    <td>${avatar}</td>
                    <td>
                        <div>
                            <strong>${user.full_name}</strong><br>
                            <small class="text-muted">@${user.username}</small><br>
                            <small class="text-muted">${user.email}</small>
                        </div>
                    </td>
                    <td>${roleBadge}</td>
                    <td>${statusBadge}</td>
                    <td><small>${lastLogin}</small></td>
                    <td><small>${created}</small></td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-info" onclick="showUserDetails(${user.id})" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-primary" onclick="showEditUserModal(${user.id})" title="Edit User">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-warning" onclick="showPermissionsModal(${user.id})" title="Permissions">
                                <i class="bi bi-key"></i>
                            </button>
                            <button class="btn btn-outline-${user.status === 'active' ? 'secondary' : 'success'}" onclick="toggleUserStatus(${user.id})" title="Toggle Status">
                                <i class="bi bi-${user.status === 'active' ? 'pause' : 'play'}"></i>
                            </button>
                            <button class="btn btn-outline-info" onclick="resetPassword(${user.id})" title="Reset Password">
                                <i class="bi bi-key-fill"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteUserConfirm(${user.id}, '${user.username}')" title="Delete User">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
    }
    
    tbody.innerHTML = html;
}

// Update statistics
function updateStatistics(users) {
    const total = users.length;
    const active = users.filter(u => u.status === 'active').length;
    const admins = users.filter(u => u.role === 'admin').length;
    const online = users.filter(u => u.active_sessions > 0).length;
    
    document.getElementById('totalUsers').textContent = total;
    document.getElementById('activeUsers').textContent = active;
    document.getElementById('adminUsers').textContent = admins;
    document.getElementById('onlineUsers').textContent = online;
}

// Helper functions for badges
function getStatusBadge(status) {
    const badges = {
        'active': '<span class="badge bg-success">Active</span>',
        'inactive': '<span class="badge bg-secondary">Inactive</span>',
        'suspended': '<span class="badge bg-danger">Suspended</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
}

function getRoleBadge(role) {
    const badges = {
        'admin': '<span class="badge bg-danger">Admin</span>',
        'manager': '<span class="badge bg-warning">Manager</span>',
        'employee': '<span class="badge bg-info">Employee</span>',
        'user': '<span class="badge bg-primary">User</span>'
    };
    return badges[role] || '<span class="badge bg-secondary">Unknown</span>';
}

function formatDateTime(dateStr) {
    if (!dateStr) return 'Never';
    const date = new Date(dateStr);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

// Filter users
function filterUsers() {
    const searchTerm = document.getElementById('userSearch').value.toLowerCase();
    const filteredUsers = usersData.filter(user => 
        user.full_name.toLowerCase().includes(searchTerm) ||
        user.username.toLowerCase().includes(searchTerm) ||
        user.email.toLowerCase().includes(searchTerm) ||
        user.role.toLowerCase().includes(searchTerm)
    );
    displayUsers(filteredUsers);
}

// Modal functions
function showAddUserModal() {
    new bootstrap.Modal(document.getElementById('addUserModal')).show();
}

function showEditUserModal(userId) {
    const user = usersData.find(u => u.id == userId);
    if (!user) return;
    
    const form = document.getElementById('editUserForm');
    const formData = new FormData(form);
    
    // Populate form
    form.querySelector('[name="user_id"]').value = user.id;
    form.querySelector('[name="username"]').value = user.username;
    form.querySelector('[name="email"]').value = user.email;
    form.querySelector('[name="full_name"]').value = user.full_name;
    form.querySelector('[name="phone"]').value = user.phone || '';
    form.querySelector('[name="role"]').value = user.role;
    form.querySelector('[name="status"]').value = user.status;
    form.querySelector('[name="department"]').value = user.department || '';
    form.querySelector('[name="position"]').value = user.position || '';
    form.querySelector('[name="hire_date"]').value = user.hire_date || '';
    form.querySelector('[name="address"]').value = user.address || '';
    
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function showPermissionsModal(userId) {
    const user = usersData.find(u => u.id == userId);
    if (!user) return;
    
    const form = document.getElementById('permissionsForm');
    form.querySelector('[name="user_id"]').value = userId;
    
    // Clear all checkboxes first
    form.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    
    // Parse and set permissions
    if (user.permissions) {
        try {
            const permissions = JSON.parse(user.permissions);
            permissions.forEach(permission => {
                const checkbox = form.querySelector(`input[value="${permission}"]`);
                if (checkbox) checkbox.checked = true;
            });
        } catch (e) {
            console.error('Error parsing permissions:', e);
        }
    }
    
    new bootstrap.Modal(document.getElementById('permissionsModal')).show();
}

// CRUD operations
function addUser() {
    const form = document.getElementById('addUserForm');
    const formData = new FormData(form);
    formData.append('action', 'add_user');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
            showAlert('success', data.message);
            form.reset();
            loadUsers();
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Network error adding user');
    });
}

function updateUser() {
    const form = document.getElementById('editUserForm');
    const formData = new FormData(form);
    formData.append('action', 'update_user');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
            showAlert('success', data.message);
            loadUsers();
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Network error updating user');
    });
}

function toggleUserStatus(userId) {
    if (confirm('Are you sure you want to change this user\'s status?')) {
        const formData = new FormData();
        formData.append('action', 'toggle_status');
        formData.append('user_id', userId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                loadUsers();
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Network error changing status');
        });
    }
}

function deleteUserConfirm(userId, username) {
    if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('user_id', userId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                loadUsers();
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Network error deleting user');
        });
    }
}

function resetPassword(userId) {
    if (confirm('Are you sure you want to reset this user\'s password?')) {
        const formData = new FormData();
        formData.append('action', 'reset_password');
        formData.append('user_id', userId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Password reset successfully! New password: ${data.new_password}\n\nPlease share this with the user and ask them to change it on first login.`);
                showAlert('success', data.message);
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Network error resetting password');
        });
    }
}

function updatePermissions() {
    const form = document.getElementById('permissionsForm');
    const formData = new FormData(form);
    
    // Get selected permissions
    const permissions = [];
    form.querySelectorAll('input[name="permissions[]"]:checked').forEach(cb => {
        permissions.push(cb.value);
    });
    
    const postData = new FormData();
    postData.append('action', 'update_permissions');
    postData.append('user_id', form.querySelector('[name="user_id"]').value);
    postData.append('permissions', JSON.stringify(permissions));
    
    fetch(window.location.href, {
        method: 'POST',
        body: postData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('permissionsModal')).hide();
            showAlert('success', data.message);
            loadUsers();
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Network error updating permissions');
    });
}

function showUserDetails(userId) {
    const modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
    modal.show();
    
    const formData = new FormData();
    formData.append('action', 'get_user_details');
    formData.append('user_id', userId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const user = data.user;
            let content = `
                <div class="row g-3">
                    <div class="col-md-4 text-center">
                        <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold" style="width: 80px; height: 80px; font-size: 2rem;">
                            ${user.full_name.charAt(0)}
                        </div>
                        <h5 class="mt-2">${user.full_name}</h5>
                        <p class="text-muted">@${user.username}</p>
                    </div>
                    <div class="col-md-8">
                        <table class="table table-sm">
                            <tr><td><strong>Email:</strong></td><td>${user.email}</td></tr>
                            <tr><td><strong>Role:</strong></td><td>${getRoleBadge(user.role)}</td></tr>
                            <tr><td><strong>Status:</strong></td><td>${getStatusBadge(user.status)}</td></tr>
                            <tr><td><strong>Department:</strong></td><td>${user.department || 'N/A'}</td></tr>
                            <tr><td><strong>Position:</strong></td><td>${user.position || 'N/A'}</td></tr>
                            <tr><td><strong>Phone:</strong></td><td>${user.phone || 'N/A'}</td></tr>
                            <tr><td><strong>Hire Date:</strong></td><td>${user.hire_date || 'N/A'}</td></tr>
                            <tr><td><strong>Total Sessions:</strong></td><td>${user.total_sessions}</td></tr>
                            <tr><td><strong>Active Sessions:</strong></td><td>${user.active_sessions}</td></tr>
                        </table>
                    </div>
                </div>
                
                <hr>
                
                <h6>Recent Activities</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Action</th><th>Description</th><th>Time</th></tr>
                        </thead>
                        <tbody>
            `;
            
            if (user.recent_activities && user.recent_activities.length > 0) {
                user.recent_activities.forEach(activity => {
                    content += `
                        <tr>
                            <td><span class="badge bg-secondary">${activity.action}</span></td>
                            <td>${activity.description}</td>
                            <td><small>${formatDateTime(activity.created_at)}</small></td>
                        </tr>
                    `;
                });
            } else {
                content += '<tr><td colspan="3" class="text-center">No recent activities</td></tr>';
            }
            
            content += '</tbody></table></div>';
            
            document.getElementById('userDetailsContent').innerHTML = content;
        } else {
            document.getElementById('userDetailsContent').innerHTML = '<div class="alert alert-danger">Error loading user details</div>';
        }
    })
    .catch(error => {
        document.getElementById('userDetailsContent').innerHTML = '<div class="alert alert-danger">Network error loading details</div>';
        console.error('Error:', error);
    });
}

// Utility functions
function refreshUserList() {
    loadUsers();
    showAlert('success', 'User list refreshed');
}

function exportUsers() {
    // Create CSV data
    const csvData = [
        ['Username', 'Full Name', 'Email', 'Role', 'Status', 'Department', 'Position', 'Created At']
    ];
    
    usersData.forEach(user => {
        csvData.push([
            user.username,
            user.full_name,
            user.email,
            user.role,
            user.status,
            user.department || '',
            user.position || '',
            user.created_at
        ]);
    });
    
    // Convert to CSV string
    const csvString = csvData.map(row => 
        row.map(field => `"${field}"`).join(',')
    ).join('\n');
    
    // Download
    const blob = new Blob([csvString], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `users_export_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showAlert('success', 'Users exported successfully');
}

function showUserReport() {
    showAlert('info', 'User Analytics report feature coming soon!');
    // Here you can implement detailed user analytics
}

function managePermissions() {
    showAlert('info', 'Permission management modal will open here');
    // Here you can implement a permissions management modal
}

function refreshUserList() {
    loadUsers();
    showAlert('success', 'User list refreshed');
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
</script>

<?php include '../../layouts/footer.php'; ?>
