<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include '../db.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize roles and permissions tables if needed
initializePermissionsTables($conn);

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($uri, '/'));
    
    // Get endpoint from path or POST data
    $endpoint = $_POST['endpoint'] ?? $_GET['endpoint'] ?? '';
    
    switch ($endpoint) {
        case 'roles':
            handleRolesRequest($conn, $method);
            break;
            
        case 'permissions':
            handlePermissionsRequest($conn, $method);
            break;
            
        case 'user_roles':
            handleUserRolesRequest($conn, $method);
            break;
            
        case 'check_permission':
            handlePermissionCheck($conn);
            break;
            
        case 'bulk_assign_roles':
            handleBulkRoleAssignment($conn);
            break;
            
        case 'role_analytics':
            handleRoleAnalytics($conn);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}

function initializePermissionsTables($conn) {
    // Create roles table
    $createRolesTable = "CREATE TABLE IF NOT EXISTS user_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) UNIQUE NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        description TEXT NULL,
        permissions JSON NULL,
        is_system BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createRolesTable)) {
        error_log("Error creating roles table: " . $conn->error);
    }
    
    // Create permissions table
    $createPermissionsTable = "CREATE TABLE IF NOT EXISTS user_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) UNIQUE NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        description TEXT NULL,
        category VARCHAR(50) DEFAULT 'general',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createPermissionsTable)) {
        error_log("Error creating permissions table: " . $conn->error);
    }
    
    // Add role column to users table if it doesn't exist
    $checkRoleColumn = "SHOW COLUMNS FROM users LIKE 'role'";
    $result = $conn->query($checkRoleColumn);
    
    if ($result->num_rows === 0) {
        $addRoleColumn = "ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'user'";
        if (!$conn->query($addRoleColumn)) {
            error_log("Error adding role column to users table: " . $conn->error);
        }
    }
    
    // Insert default permissions if not exist
    insertDefaultPermissions($conn);
    insertDefaultRoles($conn);
}

function insertDefaultPermissions($conn) {
    $defaultPermissions = [
        ['dashboard', 'Dashboard Access', 'Access to main dashboard', 'core'],
        ['users', 'User Management', 'Create, edit, delete users', 'admin'],
        ['employees', 'Employee Management', 'Manage employee records', 'hr'],
        ['attendance', 'Attendance Management', 'View and manage attendance', 'hr'],
        ['payroll', 'Payroll Management', 'Process payroll and salaries', 'hr'],
        ['reports', 'Reports', 'Generate and view reports', 'reporting'],
        ['settings', 'System Settings', 'Configure system settings', 'admin'],
        ['analytics', 'Analytics', 'View analytics and insights', 'reporting'],
        ['inventory', 'Inventory Management', 'Manage products and stock', 'operations'],
        ['invoices', 'Invoice Management', 'Create and manage invoices', 'finance'],
        ['expenses', 'Expense Management', 'Track and manage expenses', 'finance'],
        ['categories', 'Category Management', 'Manage product categories', 'operations'],
        ['suppliers', 'Supplier Management', 'Manage supplier information', 'operations'],
        ['customers', 'Customer Management', 'Manage customer information', 'operations'],
        ['transactions', 'Transaction Management', 'View and manage transactions', 'finance'],
        ['backup', 'Backup & Restore', 'System backup and restore functions', 'admin'],
        ['audit', 'Audit Log', 'View system audit logs', 'admin'],
        ['notifications', 'Notification Management', 'Manage system notifications', 'admin']
    ];
    
    foreach ($defaultPermissions as $permission) {
        $checkPerm = $conn->prepare("SELECT id FROM user_permissions WHERE name = ?");
        $checkPerm->bind_param("s", $permission[0]);
        $checkPerm->execute();
        
        if ($checkPerm->get_result()->num_rows === 0) {
            $insertPerm = $conn->prepare("INSERT INTO user_permissions (name, display_name, description, category) VALUES (?, ?, ?, ?)");
            $insertPerm->bind_param("ssss", $permission[0], $permission[1], $permission[2], $permission[3]);
            $insertPerm->execute();
        }
    }
}

function insertDefaultRoles($conn) {
    $defaultRoles = [
        ['admin', 'Administrator', 'Full system access', ['dashboard', 'users', 'employees', 'attendance', 'payroll', 'reports', 'settings', 'analytics', 'inventory', 'invoices', 'expenses', 'categories', 'suppliers', 'customers', 'transactions', 'backup', 'audit', 'notifications'], true],
        ['manager', 'Manager', 'Management level access', ['dashboard', 'employees', 'attendance', 'payroll', 'reports', 'analytics', 'inventory', 'invoices', 'expenses', 'categories', 'suppliers', 'customers', 'transactions'], true],
        ['supervisor', 'Supervisor', 'Supervisory access', ['dashboard', 'attendance', 'reports', 'inventory', 'categories'], true],
        ['employee', 'Employee', 'Employee level access', ['dashboard', 'attendance'], true],
        ['user', 'User', 'Basic user access', ['dashboard'], true]
    ];
    
    foreach ($defaultRoles as $role) {
        $checkRole = $conn->prepare("SELECT id FROM user_roles WHERE name = ?");
        $checkRole->bind_param("s", $role[0]);
        $checkRole->execute();
        
        if ($checkRole->get_result()->num_rows === 0) {
            $insertRole = $conn->prepare("INSERT INTO user_roles (name, display_name, description, permissions, is_system) VALUES (?, ?, ?, ?, ?)");
            $permissions_json = json_encode($role[3]);
            $insertRole->bind_param("ssssi", $role[0], $role[1], $role[2], $permissions_json, $role[4]);
            $insertRole->execute();
        }
    }
}

function handleRolesRequest($conn, $method) {
    switch ($method) {
        case 'GET':
            getRoles($conn);
            break;
            
        case 'POST':
            createRole($conn);
            break;
            
        case 'PUT':
            updateRole($conn);
            break;
            
        case 'DELETE':
            deleteRole($conn);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
}

function handlePermissionsRequest($conn, $method) {
    switch ($method) {
        case 'GET':
            getPermissions($conn);
            break;
            
        case 'POST':
            createPermission($conn);
            break;
            
        case 'PUT':
            updatePermission($conn);
            break;
            
        case 'DELETE':
            deletePermission($conn);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
}

function handleUserRolesRequest($conn, $method) {
    switch ($method) {
        case 'GET':
            getUserRoles($conn);
            break;
            
        case 'POST':
            assignUserRole($conn);
            break;
            
        case 'PUT':
            updateUserRole($conn);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
}

function getRoles($conn) {
    $query = "SELECT r.*, 
              (SELECT COUNT(*) FROM users WHERE role = r.name) as user_count
              FROM user_roles r 
              ORDER BY r.is_system DESC, r.created_at";
    
    $result = $conn->query($query);
    $roles = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['permissions']) {
                $row['permissions'] = json_decode($row['permissions'], true);
            }
            $roles[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'roles' => $roles]);
}

function getPermissions($conn) {
    $query = "SELECT * FROM user_permissions ORDER BY category, display_name";
    $result = $conn->query($query);
    $permissions = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'permissions' => $permissions]);
}

function createRole($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['name']) || empty($data['display_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Role name and display name are required']);
        return;
    }
    
    $query = "INSERT INTO user_roles (name, display_name, description, permissions) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    $permissions = isset($data['permissions']) ? json_encode($data['permissions']) : null;
    $stmt->bind_param("ssss", $data['name'], $data['display_name'], $data['description'], $permissions);
    
    if ($stmt->execute()) {
        logActivity($conn, "Created role: {$data['display_name']}");
        echo json_encode(['success' => true, 'message' => 'Role created successfully', 'role_id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error creating role: ' . $stmt->error]);
    }
}

function updateRole($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['role_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Role ID is required']);
        return;
    }
    
    $updates = [];
    $params = [];
    $types = "";
    
    if (isset($data['display_name'])) {
        $updates[] = "display_name = ?";
        $params[] = $data['display_name'];
        $types .= "s";
    }
    
    if (isset($data['description'])) {
        $updates[] = "description = ?";
        $params[] = $data['description'];
        $types .= "s";
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
    
    $params[] = $data['role_id'];
    $types .= "i";
    
    $query = "UPDATE user_roles SET " . implode(', ', $updates) . " WHERE id = ? AND is_system = FALSE";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            logActivity($conn, "Updated role: {$data['role_id']}");
            echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Role not found or cannot modify system role']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error updating role: ' . $stmt->error]);
    }
}

function deleteRole($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $roleId = $data['role_id'] ?? null;
    
    if (!$roleId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Role ID is required']);
        return;
    }
    
    // Check if role is in use
    $checkQuery = "SELECT COUNT(*) as count FROM users WHERE role = (SELECT name FROM user_roles WHERE id = ?)";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $roleId);
    $checkStmt->execute();
    $result = $checkStmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Cannot delete role that is assigned to users']);
        return;
    }
    
    $deleteQuery = "DELETE FROM user_roles WHERE id = ? AND is_system = FALSE";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("i", $roleId);
    
    if ($deleteStmt->execute()) {
        if ($deleteStmt->affected_rows > 0) {
            logActivity($conn, "Deleted role: $roleId");
            echo json_encode(['success' => true, 'message' => 'Role deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Role not found or cannot delete system role']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error deleting role']);
    }
}

function createPermission($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['name']) || empty($data['display_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Permission name and display name are required']);
        return;
    }
    
    $query = "INSERT INTO user_permissions (name, display_name, description, category) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    $category = $data['category'] ?? 'general';
    $stmt->bind_param("ssss", $data['name'], $data['display_name'], $data['description'], $category);
    
    if ($stmt->execute()) {
        logActivity($conn, "Created permission: {$data['display_name']}");
        echo json_encode(['success' => true, 'message' => 'Permission created successfully', 'permission_id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error creating permission: ' . $stmt->error]);
    }
}

function updatePermission($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['permission_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Permission ID is required']);
        return;
    }
    
    $updates = [];
    $params = [];
    $types = "";
    
    if (isset($data['display_name'])) {
        $updates[] = "display_name = ?";
        $params[] = $data['display_name'];
        $types .= "s";
    }
    
    if (isset($data['description'])) {
        $updates[] = "description = ?";
        $params[] = $data['description'];
        $types .= "s";
    }
    
    if (isset($data['category'])) {
        $updates[] = "category = ?";
        $params[] = $data['category'];
        $types .= "s";
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        return;
    }
    
    $params[] = $data['permission_id'];
    $types .= "i";
    
    $query = "UPDATE user_permissions SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        logActivity($conn, "Updated permission: {$data['permission_id']}");
        echo json_encode(['success' => true, 'message' => 'Permission updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error updating permission']);
    }
}

function deletePermission($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $permissionId = $data['permission_id'] ?? null;
    
    if (!$permissionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Permission ID is required']);
        return;
    }
    
    // Check if permission is in use by any role
    $checkQuery = "SELECT COUNT(*) as count FROM user_roles WHERE JSON_CONTAINS(permissions, JSON_QUOTE((SELECT name FROM user_permissions WHERE id = ?)))";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $permissionId);
    $checkStmt->execute();
    $result = $checkStmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Cannot delete permission that is assigned to roles']);
        return;
    }
    
    $deleteQuery = "DELETE FROM user_permissions WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("i", $permissionId);
    
    if ($deleteStmt->execute()) {
        logActivity($conn, "Deleted permission: $permissionId");
        echo json_encode(['success' => true, 'message' => 'Permission deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error deleting permission']);
    }
}

function updateUserRole($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['user_id']) || empty($data['role'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID and role are required']);
        return;
    }
    
    $updateQuery = "UPDATE users SET role = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("si", $data['role'], $data['user_id']);
    
    if ($updateStmt->execute()) {
        logActivity($conn, "Updated user {$data['user_id']} role to {$data['role']}");
        echo json_encode(['success' => true, 'message' => 'User role updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error updating user role']);
    }
}

function getUserRoles($conn) {
    $query = "SELECT u.id, u.username, u.email, u.role, r.display_name as role_display_name, r.permissions
              FROM users u 
              LEFT JOIN user_roles r ON u.role = r.name 
              ORDER BY u.username";
    
    $result = $conn->query($query);
    $users = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['permissions']) {
                $row['permissions'] = json_decode($row['permissions'], true);
            }
            $users[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'users' => $users]);
}

function assignUserRole($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['user_id']) || empty($data['role_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID and Role ID are required']);
        return;
    }
    
    // Get role name
    $roleQuery = "SELECT name FROM user_roles WHERE id = ?";
    $roleStmt = $conn->prepare($roleQuery);
    $roleStmt->bind_param("i", $data['role_id']);
    $roleStmt->execute();
    $roleResult = $roleStmt->get_result();
    
    if (!$role = $roleResult->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Role not found']);
        return;
    }
    
    $updateQuery = "UPDATE users SET role = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("si", $role['name'], $data['user_id']);
    
    if ($updateStmt->execute()) {
        logActivity($conn, "Assigned role {$role['name']} to user {$data['user_id']}");
        echo json_encode(['success' => true, 'message' => 'Role assigned successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error assigning role']);
    }
}

function handlePermissionCheck($conn) {
    $userId = $_GET['user_id'] ?? $_SESSION['user_id'] ?? null;
    $permission = $_GET['permission'] ?? null;
    
    if (!$userId || !$permission) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID and permission are required']);
        return;
    }
    
    $hasPermission = checkUserPermission($conn, $userId, $permission);
    echo json_encode(['success' => true, 'has_permission' => $hasPermission]);
}

function checkUserPermission($conn, $userId, $permission) {
    $query = "SELECT r.permissions 
              FROM users u 
              LEFT JOIN user_roles r ON u.role = r.name 
              WHERE u.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        if ($user['permissions']) {
            $permissions = json_decode($user['permissions'], true);
            return in_array($permission, $permissions);
        }
    }
    
    return false;
}

function handleBulkRoleAssignment($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['user_ids']) || empty($data['role_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User IDs and Role ID are required']);
        return;
    }
    
    // Get role name
    $roleQuery = "SELECT name FROM user_roles WHERE id = ?";
    $roleStmt = $conn->prepare($roleQuery);
    $roleStmt->bind_param("i", $data['role_id']);
    $roleStmt->execute();
    $roleResult = $roleStmt->get_result();
    
    if (!$role = $roleResult->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Role not found']);
        return;
    }
    
    $conn->autocommit(false);
    $success = true;
    $affected = 0;
    
    try {
        $updateQuery = "UPDATE users SET role = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        
        foreach ($data['user_ids'] as $userId) {
            $updateStmt->bind_param("si", $role['name'], $userId);
            if ($updateStmt->execute()) {
                $affected += $updateStmt->affected_rows;
            } else {
                $success = false;
                break;
            }
        }
        
        if ($success) {
            $conn->commit();
            logActivity($conn, "Bulk assigned role {$role['name']} to $affected users");
            echo json_encode(['success' => true, 'message' => "Role assigned to $affected users"]);
        } else {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error in bulk role assignment']);
        }
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
    
    $conn->autocommit(true);
}

function handleRoleAnalytics($conn) {
    $analytics = [];
    
    // Role distribution
    $roleDistQuery = "SELECT r.display_name as role_name, COUNT(u.id) as user_count
                      FROM user_roles r
                      LEFT JOIN users u ON u.role = r.name
                      GROUP BY r.id, r.display_name
                      ORDER BY user_count DESC";
    
    $result = $conn->query($roleDistQuery);
    $roleDistribution = [];
    while ($row = $result->fetch_assoc()) {
        $roleDistribution[] = $row;
    }
    
    // Permission usage
    $permissionQuery = "SELECT p.display_name as permission_name, p.category,
                        COUNT(CASE WHEN JSON_CONTAINS(r.permissions, JSON_QUOTE(p.name)) THEN 1 END) as role_count
                        FROM user_permissions p
                        LEFT JOIN user_roles r ON JSON_CONTAINS(r.permissions, JSON_QUOTE(p.name))
                        GROUP BY p.id, p.display_name, p.category
                        ORDER BY role_count DESC";
    
    $result = $conn->query($permissionQuery);
    $permissionUsage = [];
    while ($row = $result->fetch_assoc()) {
        $permissionUsage[] = $row;
    }
    
    // Total counts
    $totals = [
        'total_roles' => $conn->query("SELECT COUNT(*) as count FROM user_roles")->fetch_assoc()['count'],
        'total_permissions' => $conn->query("SELECT COUNT(*) as count FROM user_permissions")->fetch_assoc()['count'],
        'total_users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
        'system_roles' => $conn->query("SELECT COUNT(*) as count FROM user_roles WHERE is_system = TRUE")->fetch_assoc()['count']
    ];
    
    $analytics = [
        'role_distribution' => $roleDistribution,
        'permission_usage' => $permissionUsage,
        'totals' => $totals
    ];
    
    echo json_encode(['success' => true, 'analytics' => $analytics]);
}

function logActivity($conn, $activity) {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $query = "INSERT INTO user_activity_log (user_id, activity, ip_address, user_agent) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt->bind_param("isss", $userId, $activity, $ipAddress, $userAgent);
        $stmt->execute();
    }
}
?>
