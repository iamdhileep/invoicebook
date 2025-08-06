<?php
/**
 * Permission Management API - Handle all permission-related operations
 */
session_start();
require_once '../db.php';
require_once '../auth_guard.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isCurrentUserAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $userPermission = new UserPermission($conn);
    
    switch ($action) {
        case 'get_users':
            getUsersList($conn);
            break;
            
        case 'get_user_permissions':
            getUserPermissions($conn, $userPermission);
            break;
            
        case 'grant_hrms_permission':
            grantHRMSPermission($conn, $userPermission);
            break;
            
        case 'grant_group_permission':
            grantGroupPermission($conn, $userPermission);
            break;
            
        case 'assign_role':
            assignUserRole($conn, $userPermission);
            break;
            
        case 'revoke_permission':
            revokePermission($conn);
            break;
            
        case 'get_roles':
            getRolesList($conn);
            break;
            
        case 'get_hrms_pages':
            getHRMSPagesList($userPermission);
            break;
            
        case 'get_permission_groups':
            getPermissionGroupsList($userPermission);
            break;
            
        case 'bulk_grant_permissions':
            bulkGrantPermissions($conn, $userPermission);
            break;
            
        case 'create_role':
            createCustomRole($conn);
            break;
            
        case 'update_role':
            updateRole($conn);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    error_log("Permission API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Get users list with their roles
 */
function getUsersList($conn) {
    $sql = "SELECT u.id, u.username, u.email, u.role as default_role,
                   GROUP_CONCAT(ur.role_name) as assigned_roles,
                   COUNT(up.id) as permission_count
            FROM users u
            LEFT JOIN user_role_assignments ura ON u.id = ura.user_id AND ura.is_active = 1
            LEFT JOIN user_roles ur ON ura.role_id = ur.id
            LEFT JOIN user_permissions up ON u.id = up.user_id
            GROUP BY u.id
            ORDER BY u.username";
    
    $result = $conn->query($sql);
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $users]);
}

/**
 * Get user's detailed permissions
 */
function getUserPermissions($conn, $userPermission) {
    $user_id = intval($_POST['user_id']);
    
    if (!$user_id) {
        throw new Exception('User ID is required');
    }
    
    $permissions = $userPermission->getUserPermissionsSummary($user_id);
    echo json_encode(['success' => true, 'data' => $permissions]);
}

/**
 * Grant HRMS page permission
 */
function grantHRMSPermission($conn, $userPermission) {
    $user_id = intval($_POST['user_id']);
    $page_file = $_POST['page_file'] ?? '';
    $permissions = $_POST['permissions'] ?? [];
    $granted_by = getCurrentUserId();
    
    if (!$user_id || !$page_file) {
        throw new Exception('User ID and page file are required');
    }
    
    $permission_set = [
        'can_view' => isset($permissions['can_view']) ? 1 : 0,
        'can_add' => isset($permissions['can_add']) ? 1 : 0,
        'can_edit' => isset($permissions['can_edit']) ? 1 : 0,
        'can_delete' => isset($permissions['can_delete']) ? 1 : 0,
        'can_approve' => isset($permissions['can_approve']) ? 1 : 0,
        'can_export' => isset($permissions['can_export']) ? 1 : 0
    ];
    
    $success = $userPermission->grantHRMSPagePermission($user_id, $page_file, $permission_set, $granted_by);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'HRMS permission granted successfully']);
    } else {
        throw new Exception('Failed to grant HRMS permission');
    }
}

/**
 * Grant group permission
 */
function grantGroupPermission($conn, $userPermission) {
    $user_id = intval($_POST['user_id']);
    $group_name = $_POST['group_name'] ?? '';
    $permissions = $_POST['permissions'] ?? [];
    $granted_by = getCurrentUserId();
    
    if (!$user_id || !$group_name) {
        throw new Exception('User ID and group name are required');
    }
    
    $permission_set = [
        'can_view' => isset($permissions['can_view']) ? 1 : 0,
        'can_add' => isset($permissions['can_add']) ? 1 : 0,
        'can_edit' => isset($permissions['can_edit']) ? 1 : 0,
        'can_delete' => isset($permissions['can_delete']) ? 1 : 0,
        'can_approve' => isset($permissions['can_approve']) ? 1 : 0,
        'can_export' => isset($permissions['can_export']) ? 1 : 0
    ];
    
    $success = $userPermission->grantGroupPermission($user_id, $group_name, $permission_set, $granted_by);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Group permission granted successfully']);
    } else {
        throw new Exception('Failed to grant group permission');
    }
}

/**
 * Assign role to user
 */
function assignUserRole($conn, $userPermission) {
    $user_id = intval($_POST['user_id']);
    $role_name = $_POST['role_name'] ?? '';
    $assigned_by = getCurrentUserId();
    
    if (!$user_id || !$role_name) {
        throw new Exception('User ID and role name are required');
    }
    
    $success = $userPermission->assignRole($user_id, $role_name, $assigned_by);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Role assigned successfully']);
    } else {
        throw new Exception('Failed to assign role');
    }
}

/**
 * Revoke permission
 */
function revokePermission($conn) {
    $permission_id = intval($_POST['permission_id']);
    
    if (!$permission_id) {
        throw new Exception('Permission ID is required');
    }
    
    $sql = "DELETE FROM user_permissions WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $permission_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Permission revoked successfully']);
    } else {
        throw new Exception('Failed to revoke permission');
    }
}

/**
 * Get roles list
 */
function getRolesList($conn) {
    $sql = "SELECT * FROM user_roles WHERE is_active = 1 ORDER BY role_name";
    $result = $conn->query($sql);
    $roles = [];
    
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $roles]);
}

/**
 * Get HRMS pages list
 */
function getHRMSPagesList($userPermission) {
    $pages = $userPermission->getHRMSPages();
    echo json_encode(['success' => true, 'data' => $pages]);
}

/**
 * Get permission groups list
 */
function getPermissionGroupsList($userPermission) {
    $groups = $userPermission->getPermissionGroups();
    echo json_encode(['success' => true, 'data' => $groups]);
}

/**
 * Bulk grant permissions
 */
function bulkGrantPermissions($conn, $userPermission) {
    $user_id = intval($_POST['user_id']);
    $permission_template = $_POST['template'] ?? '';
    $granted_by = getCurrentUserId();
    
    if (!$user_id || !$permission_template) {
        throw new Exception('User ID and permission template are required');
    }
    
    $conn->begin_transaction();
    
    try {
        // Define permission templates
        $templates = [
            'hr_manager' => [
                'hrms_full_access' => true,
                'groups' => ['Reports Access', 'Export Functions', 'Employee Basic']
            ],
            'hr_executive' => [
                'hrms_pages' => ['employee_directory.php', 'leave_management.php', 'attendance_management.php'],
                'groups' => ['Reports Access', 'Employee Basic']
            ],
            'manager' => [
                'hrms_pages' => ['team_manager_console.php', 'staff_self_service.php'],
                'groups' => ['Dashboard Access', 'Reports Access']
            ],
            'employee' => [
                'hrms_pages' => ['employee_self_service.php', 'staff_self_service.php'],
                'groups' => ['Dashboard Access']
            ]
        ];
        
        if (!isset($templates[$permission_template])) {
            throw new Exception('Invalid permission template');
        }
        
        $template = $templates[$permission_template];
        $success_count = 0;
        
        // Grant HRMS permissions
        if (isset($template['hrms_full_access']) && $template['hrms_full_access']) {
            $pages = $userPermission->getHRMSPages();
            foreach ($pages as $page) {
                $permissions = ['can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_approve' => 1, 'can_export' => 1];
                if ($userPermission->grantHRMSPagePermission($user_id, $page['page_file'], $permissions, $granted_by)) {
                    $success_count++;
                }
            }
        } elseif (isset($template['hrms_pages'])) {
            foreach ($template['hrms_pages'] as $page_file) {
                $permissions = ['can_view' => 1, 'can_add' => 1, 'can_edit' => 1];
                if ($userPermission->grantHRMSPagePermission($user_id, $page_file, $permissions, $granted_by)) {
                    $success_count++;
                }
            }
        }
        
        // Grant group permissions
        if (isset($template['groups'])) {
            foreach ($template['groups'] as $group_name) {
                $permissions = ['can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_export' => 1];
                if ($userPermission->grantGroupPermission($user_id, $group_name, $permissions, $granted_by)) {
                    $success_count++;
                }
            }
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Bulk permissions granted successfully ($success_count permissions)"]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Create custom role
 */
function createCustomRole($conn) {
    $role_name = $_POST['role_name'] ?? '';
    $role_description = $_POST['role_description'] ?? '';
    
    if (!$role_name) {
        throw new Exception('Role name is required');
    }
    
    $sql = "INSERT INTO user_roles (role_name, role_description) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $role_name, $role_description);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Role created successfully', 'role_id' => $conn->insert_id]);
    } else {
        throw new Exception('Failed to create role');
    }
}

/**
 * Update role
 */
function updateRole($conn) {
    $role_id = intval($_POST['role_id']);
    $role_name = $_POST['role_name'] ?? '';
    $role_description = $_POST['role_description'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (!$role_id || !$role_name) {
        throw new Exception('Role ID and name are required');
    }
    
    $sql = "UPDATE user_roles SET role_name = ?, role_description = ?, is_active = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssii', $role_name, $role_description, $is_active, $role_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
    } else {
        throw new Exception('Failed to update role');
    }
}
?>
