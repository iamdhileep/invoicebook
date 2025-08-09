<?php
/**
 * User Management API
 * Provides REST API endpoints for user management operations
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Authentication check
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../db.php';

$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// Get the action from POST data or URL
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        handleGetRequest($conn, $action);
        break;
    case 'POST':
        handlePostRequest($conn, $action);
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';
        handlePostRequest($conn, $action);
        break;
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? 'delete_user';
        handlePostRequest($conn, $action);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}

function handleGetRequest($conn, $action) {
    switch ($action) {
        case 'users':
            getUsersList($conn);
            break;
        case 'user':
            getUserById($conn, $_GET['id'] ?? 0);
            break;
        case 'stats':
            getUserStats($conn);
            break;
        case 'roles':
            getRoles($conn);
            break;
        case 'permissions':
            getPermissions($conn);
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
            break;
    }
}

function handlePostRequest($conn, $action) {
    switch ($action) {
        case 'create_user':
            createUser($conn);
            break;
        case 'update_user':
            updateUser($conn);
            break;
        case 'delete_user':
            deleteUser($conn);
            break;
        case 'toggle_status':
            toggleUserStatusAPI($conn);
            break;
        case 'reset_password':
            resetPasswordAPI($conn);
            break;
        case 'change_password':
            changePasswordAPI($conn);
            break;
        case 'update_permissions':
            updatePermissionsAPI($conn);
            break;
        case 'bulk_action':
            bulkAction($conn);
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Action not found']);
            break;
    }
}

function getUsersList($conn) {
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 25;
    $search = $_GET['search'] ?? '';
    $role = $_GET['role'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    $where = "WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $where .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
        $types .= "sss";
    }
    
    if (!empty($role)) {
        $where .= " AND u.role = ?";
        $params[] = $role;
        $types .= "s";
    }
    
    if (!empty($status)) {
        $where .= " AND u.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM users u $where";
    if (!empty($params)) {
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $totalResult = $countStmt->get_result();
    } else {
        $totalResult = $conn->query($countQuery);
    }
    $total = $totalResult->fetch_assoc()['total'];
    
    // Get users
    $query = "SELECT u.*, 
              COALESCE(creator.username, 'System') as created_by_name,
              (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.id AND status = 'active') as active_sessions
              FROM users u
              LEFT JOIN users creator ON u.created_by = creator.id
              $where
              ORDER BY u.created_at DESC
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        unset($row['password']); // Don't return passwords
        $users[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'pagination' => [
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getUserById($conn, $userId) {
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    $query = "SELECT u.*, 
              COALESCE(creator.username, 'System') as created_by_name,
              (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.id) as total_sessions,
              (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.id AND status = 'active') as active_sessions
              FROM users u
              LEFT JOIN users creator ON u.created_by = creator.id
              WHERE u.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        unset($user['password']);
        
        // Get user activities
        $activityQuery = "SELECT * FROM user_activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
        $activityStmt = $conn->prepare($activityQuery);
        $activityStmt->bind_param("i", $userId);
        $activityStmt->execute();
        $activityResult = $activityStmt->get_result();
        
        $activities = [];
        while ($activity = $activityResult->fetch_assoc()) {
            $activities[] = $activity;
        }
        
        $user['activities'] = $activities;
        
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
}

function getUserStats($conn) {
    $stats = [];
    
    // Total users
    $totalResult = $conn->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $totalResult->fetch_assoc()['count'];
    
    // Active users
    $activeResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $stats['active_users'] = $activeResult->fetch_assoc()['count'];
    
    // Users by role
    $roleResult = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $stats['users_by_role'] = [];
    while ($row = $roleResult->fetch_assoc()) {
        $stats['users_by_role'][$row['role']] = $row['count'];
    }
    
    // Online users (active sessions)
    $onlineResult = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM user_sessions WHERE status = 'active'");
    $stats['online_users'] = $onlineResult->fetch_assoc()['count'];
    
    // Recent signups (last 30 days)
    $recentResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['recent_signups'] = $recentResult->fetch_assoc()['count'];
    
    echo json_encode(['success' => true, 'stats' => $stats]);
}

function createUser($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $required = ['username', 'email', 'full_name', 'password', 'role'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Field $field is required"]);
            return;
        }
    }
    
    // Check if username or email exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $checkStmt->bind_param("ss", $data['username'], $data['email']);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        return;
    }
    
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    $createdBy = $_SESSION['admin'] ?? $_SESSION['user_id'];
    
    $query = "INSERT INTO users (username, email, password, full_name, role, status, phone, address, department, position, hire_date, created_by, permissions) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $permissions = isset($data['permissions']) ? json_encode($data['permissions']) : null;
    $status = $data['status'] ?? 'active';
    
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
        $createdBy,
        $permissions
    );
    
    if ($stmt->execute()) {
        $userId = $conn->insert_id;
        
        // Log activity
        logActivity($conn, $createdBy, 'user_created', "Created user: {$data['username']}");
        
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'User created successfully', 'user_id' => $userId]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error creating user: ' . $stmt->error]);
    }
}

function updateUser($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    if (empty($data['user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    $updates = [];
    $params = [];
    $types = "";
    
    $allowed = ['username', 'email', 'full_name', 'role', 'status', 'phone', 'address', 'department', 'position', 'hire_date'];
    
    foreach ($allowed as $field) {
        if (isset($data[$field])) {
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
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        return;
    }
    
    $params[] = $data['user_id'];
    $types .= "i";
    
    $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        logActivity($conn, $_SESSION['admin'] ?? $_SESSION['user_id'], 'user_updated', "Updated user ID: {$data['user_id']}");
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error updating user: ' . $stmt->error]);
    }
}

function deleteUser($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $userId = $data['user_id'] ?? 0;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    // Prevent self-deletion
    if ($userId == ($_SESSION['admin'] ?? $_SESSION['user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        return;
    }
    
    // Get username for logging
    $userStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    if (!$user = $userResult->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $deleteStmt->bind_param("i", $userId);
    
    if ($deleteStmt->execute()) {
        logActivity($conn, $_SESSION['admin'] ?? $_SESSION['user_id'], 'user_deleted', "Deleted user: {$user['username']}");
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error deleting user']);
    }
}

function bulkAction($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $action = $data['bulk_action'] ?? '';
    $userIds = $data['user_ids'] ?? [];
    
    if (empty($action) || empty($userIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action and user IDs required']);
        return;
    }
    
    $currentUserId = $_SESSION['admin'] ?? $_SESSION['user_id'];
    $successCount = 0;
    $errors = [];
    
    foreach ($userIds as $userId) {
        // Skip current user for delete action
        if ($action === 'delete' && $userId == $currentUserId) {
            $errors[] = "Cannot delete your own account (ID: $userId)";
            continue;
        }
        
        switch ($action) {
            case 'activate':
                $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                break;
            case 'deactivate':
                $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                break;
            case 'suspend':
                $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
                break;
            case 'delete':
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                return;
        }
        
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            $successCount++;
        } else {
            $errors[] = "Failed to $action user ID: $userId";
        }
    }
    
    logActivity($conn, $currentUserId, 'bulk_action', "Bulk $action on $successCount users");
    
    echo json_encode([
        'success' => true,
        'message' => "Bulk action completed: $successCount users processed",
        'success_count' => $successCount,
        'errors' => $errors
    ]);
}

function toggleUserStatusAPI($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $userId = $data['user_id'] ?? 0;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    $statusStmt = $conn->prepare("SELECT status, username FROM users WHERE id = ?");
    $statusStmt->bind_param("i", $userId);
    $statusStmt->execute();
    $result = $statusStmt->get_result();
    
    if (!$user = $result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
    
    $updateStmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $updateStmt->bind_param("si", $newStatus, $userId);
    
    if ($updateStmt->execute()) {
        logActivity($conn, $_SESSION['admin'] ?? $_SESSION['user_id'], 'status_changed', "Changed status of {$user['username']} to $newStatus");
        echo json_encode(['success' => true, 'message' => "User status changed to $newStatus", 'new_status' => $newStatus]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error updating status']);
    }
}

function resetPasswordAPI($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $userId = $data['user_id'] ?? 0;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    $newPassword = generateRandomPassword();
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $updateStmt->bind_param("si", $hashedPassword, $userId);
    
    if ($updateStmt->execute()) {
        $userStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $user = $userResult->fetch_assoc();
        
        logActivity($conn, $_SESSION['admin'] ?? $_SESSION['user_id'], 'password_reset', "Reset password for user: {$user['username']}");
        echo json_encode(['success' => true, 'message' => 'Password reset successfully', 'new_password' => $newPassword]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error resetting password']);
    }
}

function changePasswordAPI($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $userId = $data['user_id'] ?? ($_SESSION['admin'] ?? $_SESSION['user_id']);
    $currentPassword = $data['current_password'] ?? '';
    $newPassword = $data['new_password'] ?? '';
    
    if (!$userId || !$currentPassword || !$newPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID, current password, and new password required']);
        return;
    }
    
    $userStmt = $conn->prepare("SELECT password, username FROM users WHERE id = ?");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    if (!$user = $userResult->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    if (!password_verify($currentPassword, $user['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        return;
    }
    
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $updateStmt->bind_param("si", $hashedPassword, $userId);
    
    if ($updateStmt->execute()) {
        logActivity($conn, $userId, 'password_changed', "Changed own password");
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error changing password']);
    }
}

function updatePermissionsAPI($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $userId = $data['user_id'] ?? 0;
    $permissions = $data['permissions'] ?? [];
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    $permissionsJson = is_array($permissions) ? json_encode($permissions) : $permissions;
    
    $updateStmt = $conn->prepare("UPDATE users SET permissions = ? WHERE id = ?");
    $updateStmt->bind_param("si", $permissionsJson, $userId);
    
    if ($updateStmt->execute()) {
        logActivity($conn, $_SESSION['admin'] ?? $_SESSION['user_id'], 'permissions_updated', "Updated permissions for user ID: $userId");
        echo json_encode(['success' => true, 'message' => 'Permissions updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error updating permissions']);
    }
}

function generateRandomPassword($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

function logActivity($conn, $userId, $action, $description) {
    if (!$userId) return;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $action, $description, $ip);
    $stmt->execute();
}

function getRoles($conn) {
    $roles = [
        'admin' => 'Administrator',
        'manager' => 'Manager', 
        'employee' => 'Employee',
        'user' => 'User'
    ];
    
    echo json_encode(['success' => true, 'roles' => $roles]);
}

function getPermissions($conn) {
    $permissions = [
        'dashboard' => 'Dashboard Access',
        'users' => 'User Management',
        'employees' => 'Employee Management',
        'attendance' => 'Attendance Management',
        'payroll' => 'Payroll Management',
        'reports' => 'Reports',
        'settings' => 'System Settings',
        'analytics' => 'Analytics'
    ];
    
    echo json_encode(['success' => true, 'permissions' => $permissions]);
}
?>
