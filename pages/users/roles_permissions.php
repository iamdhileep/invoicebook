<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';

$page_title = 'Roles & Permissions';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_roles':
            echo json_encode(getAllRoles($conn));
            exit;
            
        case 'create_role':
            $result = createRole($conn, $_POST);
            echo json_encode($result);
            exit;
            
        case 'update_role':
            $result = updateRole($conn, $_POST);
            echo json_encode($result);
            exit;
            
        case 'delete_role':
            $result = deleteRole($conn, $_POST['role_id']);
            echo json_encode($result);
            exit;
            
        case 'get_permissions':
            echo json_encode(getAllPermissions($conn));
            exit;
            
        case 'update_role_permissions':
            $result = updateRolePermissions($conn, $_POST['role_id'], $_POST['permissions']);
            echo json_encode($result);
            exit;
            
        case 'assign_role_to_user':
            $result = assignRoleToUser($conn, $_POST['user_id'], $_POST['role_id']);
            echo json_encode($result);
            exit;
    }
}

// Initialize roles and permissions tables
initializeRolesTables($conn);

function initializeRolesTables($conn) {
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
    
    // Insert default roles if not exist
    $defaultRoles = [
        ['admin', 'Administrator', 'Full system access', true],
        ['manager', 'Manager', 'Management level access', true],
        ['employee', 'Employee', 'Employee level access', true],
        ['user', 'User', 'Basic user access', true]
    ];
    
    foreach ($defaultRoles as $role) {
        $checkRole = $conn->prepare("SELECT id FROM user_roles WHERE name = ?");
        $checkRole->bind_param("s", $role[0]);
        $checkRole->execute();
        
        if ($checkRole->get_result()->num_rows === 0) {
            $insertRole = $conn->prepare("INSERT INTO user_roles (name, display_name, description, is_system) VALUES (?, ?, ?, ?)");
            $insertRole->bind_param("sssi", $role[0], $role[1], $role[2], $role[3]);
            $insertRole->execute();
        }
    }
    
    // Insert default permissions if not exist
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
        ['categories', 'Category Management', 'Manage product categories', 'operations']
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

function getAllRoles($conn) {
    $query = "SELECT r.*, 
              (SELECT COUNT(*) FROM users WHERE role = r.name) as user_count
              FROM user_roles r 
              ORDER BY r.created_at";
    
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
    
    return ['success' => true, 'roles' => $roles];
}

function getAllPermissions($conn) {
    $query = "SELECT * FROM user_permissions ORDER BY category, name";
    $result = $conn->query($query);
    $permissions = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row;
        }
    }
    
    return ['success' => true, 'permissions' => $permissions];
}

function createRole($conn, $data) {
    if (empty($data['name']) || empty($data['display_name'])) {
        return ['success' => false, 'message' => 'Role name and display name are required'];
    }
    
    $query = "INSERT INTO user_roles (name, display_name, description, permissions) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    $permissions = isset($data['permissions']) ? json_encode($data['permissions']) : null;
    $stmt->bind_param("ssss", $data['name'], $data['display_name'], $data['description'], $permissions);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Role created successfully', 'role_id' => $conn->insert_id];
    } else {
        return ['success' => false, 'message' => 'Error creating role: ' . $stmt->error];
    }
}

function updateRole($conn, $data) {
    if (empty($data['role_id'])) {
        return ['success' => false, 'message' => 'Role ID is required'];
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
        return ['success' => false, 'message' => 'No fields to update'];
    }
    
    $params[] = $data['role_id'];
    $types .= "i";
    
    $query = "UPDATE user_roles SET " . implode(', ', $updates) . " WHERE id = ? AND is_system = FALSE";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            return ['success' => true, 'message' => 'Role updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Role not found or cannot modify system role'];
        }
    } else {
        return ['success' => false, 'message' => 'Error updating role: ' . $stmt->error];
    }
}

function deleteRole($conn, $roleId) {
    // Check if role is in use
    $checkQuery = "SELECT COUNT(*) as count FROM users WHERE role = (SELECT name FROM user_roles WHERE id = ?)";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $roleId);
    $checkStmt->execute();
    $result = $checkStmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        return ['success' => false, 'message' => 'Cannot delete role that is assigned to users'];
    }
    
    $deleteQuery = "DELETE FROM user_roles WHERE id = ? AND is_system = FALSE";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("i", $roleId);
    
    if ($deleteStmt->execute()) {
        if ($deleteStmt->affected_rows > 0) {
            return ['success' => true, 'message' => 'Role deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Role not found or cannot delete system role'];
        }
    } else {
        return ['success' => false, 'message' => 'Error deleting role'];
    }
}

function updateRolePermissions($conn, $roleId, $permissions) {
    $permissionsJson = is_array($permissions) ? json_encode($permissions) : $permissions;
    
    $query = "UPDATE user_roles SET permissions = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $permissionsJson, $roleId);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Role permissions updated successfully'];
    } else {
        return ['success' => false, 'message' => 'Error updating permissions'];
    }
}

function assignRoleToUser($conn, $userId, $roleId) {
    // Get role name
    $roleQuery = "SELECT name FROM user_roles WHERE id = ?";
    $roleStmt = $conn->prepare($roleQuery);
    $roleStmt->bind_param("i", $roleId);
    $roleStmt->execute();
    $roleResult = $roleStmt->get_result();
    
    if (!$role = $roleResult->fetch_assoc()) {
        return ['success' => false, 'message' => 'Role not found'];
    }
    
    $updateQuery = "UPDATE users SET role = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("si", $role['name'], $userId);
    
    if ($updateStmt->execute()) {
        return ['success' => true, 'message' => 'Role assigned successfully'];
    } else {
        return ['success' => false, 'message' => 'Error assigning role'];
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
                    <h1 class="h4 mb-1 fw-bold text-primary">🔐 Roles & Permissions</h1>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-shield-lock"></i> 
                        Configure user roles and access permissions
                        <span class="badge bg-light text-dark ms-2">Security Management</span>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success btn-sm" onclick="showCreateRoleModal()">
                        <i class="bi bi-plus-lg"></i> Create Role
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshData()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="row g-4">
                <!-- Roles Management -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h6 class="mb-0 fw-semibold">
                                <i class="bi bi-person-badge"></i> User Roles
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Role</th>
                                            <th>Description</th>
                                            <th>Users</th>
                                            <th>Type</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="rolesTableBody">
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permissions List -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h6 class="mb-0 fw-semibold">
                                <i class="bi bi-key"></i> Available Permissions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="permissionsList">
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
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

<!-- Create Role Modal -->
<div class="modal fade" id="createRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-lg"></i> Create New Role
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createRoleForm">
                    <div class="mb-3">
                        <label class="form-label">Role Name *</label>
                        <input type="text" class="form-control" name="name" required placeholder="e.g., supervisor">
                        <div class="form-text">Lowercase, no spaces (used internally)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Display Name *</label>
                        <input type="text" class="form-control" name="display_name" required placeholder="e.g., Supervisor">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Brief description of this role"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <div class="border p-3 rounded" style="max-height: 200px; overflow-y: auto;" id="permissionsCheckboxes">
                            <!-- Permissions will be loaded here -->
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="createRole()">
                    <i class="bi bi-plus-lg"></i> Create Role
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-pencil"></i> Edit Role
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editRoleForm">
                    <input type="hidden" name="role_id">
                    <div class="mb-3">
                        <label class="form-label">Role Name</label>
                        <input type="text" class="form-control" name="name" readonly>
                        <div class="form-text">Role name cannot be changed</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Display Name *</label>
                        <input type="text" class="form-control" name="display_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <div class="border p-3 rounded" style="max-height: 200px; overflow-y: auto;" id="editPermissionsCheckboxes">
                            <!-- Permissions will be loaded here -->
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateRole()">
                    <i class="bi bi-check-lg"></i> Update Role
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Role Permissions Modal -->
<div class="modal fade" id="rolePermissionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-key"></i> Role Permissions
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rolePermissionsForm">
                    <input type="hidden" name="role_id">
                    <div class="mb-3">
                        <h6 id="rolePermissionsTitle">Role Permissions</h6>
                        <div id="rolePermissionsList" class="row g-2">
                            <!-- Permissions will be loaded here -->
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="updateRolePermissions()">
                    <i class="bi bi-key"></i> Update Permissions
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let rolesData = [];
let permissionsData = [];

document.addEventListener('DOMContentLoaded', function() {
    loadRoles();
    loadPermissions();
});

function loadRoles() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_roles'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            rolesData = data.roles;
            displayRoles(data.roles);
        }
    })
    .catch(error => console.error('Error loading roles:', error));
}

function loadPermissions() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_permissions'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            permissionsData = data.permissions;
            displayPermissions(data.permissions);
        }
    })
    .catch(error => console.error('Error loading permissions:', error));
}

function displayRoles(roles) {
    const tbody = document.getElementById('rolesTableBody');
    let html = '';
    
    if (roles.length === 0) {
        html = '<tr><td colspan="5" class="text-center py-4">No roles found</td></tr>';
    } else {
        roles.forEach(role => {
            const typebadge = role.is_system ? 
                '<span class="badge bg-secondary">System</span>' : 
                '<span class="badge bg-primary">Custom</span>';
            
            const permissionCount = role.permissions ? role.permissions.length : 0;
            
            html += `
                <tr>
                    <td>
                        <div>
                            <strong>${role.display_name}</strong><br>
                            <small class="text-muted">${role.name}</small>
                        </div>
                    </td>
                    <td>
                        <small>${role.description || 'No description'}</small><br>
                        <small class="text-muted">${permissionCount} permissions</small>
                    </td>
                    <td>
                        <span class="badge bg-info">${role.user_count}</span>
                    </td>
                    <td>${typebadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-warning" onclick="showRolePermissionsModal(${role.id})" title="Manage Permissions">
                                <i class="bi bi-key"></i>
                            </button>
                            ${!role.is_system ? `
                                <button class="btn btn-outline-primary" onclick="showEditRoleModal(${role.id})" title="Edit Role">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="deleteRoleConfirm(${role.id}, '${role.display_name}')" title="Delete Role">
                                    <i class="bi bi-trash"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });
    }
    
    tbody.innerHTML = html;
}

function displayPermissions(permissions) {
    const container = document.getElementById('permissionsList');
    let html = '';
    
    // Group permissions by category
    const categories = {};
    permissions.forEach(perm => {
        if (!categories[perm.category]) {
            categories[perm.category] = [];
        }
        categories[perm.category].push(perm);
    });
    
    Object.keys(categories).forEach(category => {
        html += `<h6 class="text-capitalize fw-semibold mt-3 mb-2">${category}</h6>`;
        categories[category].forEach(perm => {
            html += `
                <div class="d-flex justify-content-between align-items-center p-2 mb-1 bg-light rounded">
                    <div>
                        <small class="fw-semibold">${perm.display_name}</small><br>
                        <small class="text-muted">${perm.description}</small>
                    </div>
                    <small class="text-muted">${perm.name}</small>
                </div>
            `;
        });
    });
    
    container.innerHTML = html;
}

function showCreateRoleModal() {
    populatePermissionsCheckboxes('permissionsCheckboxes');
    new bootstrap.Modal(document.getElementById('createRoleModal')).show();
}

function showEditRoleModal(roleId) {
    const role = rolesData.find(r => r.id == roleId);
    if (!role || role.is_system) {
        showAlert('warning', 'Cannot edit system roles');
        return;
    }
    
    const form = document.getElementById('editRoleForm');
    form.querySelector('[name="role_id"]').value = role.id;
    form.querySelector('[name="name"]').value = role.name;
    form.querySelector('[name="display_name"]').value = role.display_name;
    form.querySelector('[name="description"]').value = role.description || '';
    
    populatePermissionsCheckboxes('editPermissionsCheckboxes', role.permissions || []);
    new bootstrap.Modal(document.getElementById('editRoleModal')).show();
}

function showRolePermissionsModal(roleId) {
    const role = rolesData.find(r => r.id == roleId);
    if (!role) return;
    
    document.getElementById('rolePermissionsTitle').textContent = `${role.display_name} Permissions`;
    document.getElementById('rolePermissionsForm').querySelector('[name="role_id"]').value = roleId;
    
    populateRolePermissionsCheckboxes(role.permissions || []);
    new bootstrap.Modal(document.getElementById('rolePermissionsModal')).show();
}

function populatePermissionsCheckboxes(containerId, selectedPermissions = []) {
    const container = document.getElementById(containerId);
    let html = '';
    
    const categories = {};
    permissionsData.forEach(perm => {
        if (!categories[perm.category]) {
            categories[perm.category] = [];
        }
        categories[perm.category].push(perm);
    });
    
    Object.keys(categories).forEach(category => {
        html += `<div class="mb-2"><strong class="text-capitalize">${category}</strong></div>`;
        categories[category].forEach(perm => {
            const checked = selectedPermissions.includes(perm.name) ? 'checked' : '';
            html += `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="permissions[]" value="${perm.name}" ${checked} id="perm_${perm.name}_${containerId}">
                    <label class="form-check-label" for="perm_${perm.name}_${containerId}">
                        ${perm.display_name}
                        <small class="text-muted d-block">${perm.description}</small>
                    </label>
                </div>
            `;
        });
        html += '<hr>';
    });
    
    container.innerHTML = html;
}

function populateRolePermissionsCheckboxes(selectedPermissions = []) {
    const container = document.getElementById('rolePermissionsList');
    let html = '';
    
    const categories = {};
    permissionsData.forEach(perm => {
        if (!categories[perm.category]) {
            categories[perm.category] = [];
        }
        categories[perm.category].push(perm);
    });
    
    Object.keys(categories).forEach(category => {
        html += `
            <div class="col-12 mb-2">
                <h6 class="text-capitalize text-primary">${category}</h6>
            </div>
        `;
        
        categories[category].forEach(perm => {
            const checked = selectedPermissions.includes(perm.name) ? 'checked' : '';
            html += `
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="role_permissions[]" value="${perm.name}" ${checked} id="role_perm_${perm.name}">
                        <label class="form-check-label" for="role_perm_${perm.name}">
                            <span class="fw-semibold">${perm.display_name}</span>
                            <small class="text-muted d-block">${perm.description}</small>
                        </label>
                    </div>
                </div>
            `;
        });
    });
    
    container.innerHTML = html;
}

function createRole() {
    const form = document.getElementById('createRoleForm');
    const formData = new FormData(form);
    
    // Get selected permissions
    const permissions = [];
    form.querySelectorAll('input[name="permissions[]"]:checked').forEach(cb => {
        permissions.push(cb.value);
    });
    
    formData.append('action', 'create_role');
    formData.append('permissions', JSON.stringify(permissions));
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('createRoleModal')).hide();
            showAlert('success', data.message);
            form.reset();
            loadRoles();
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Network error creating role');
    });
}

function updateRole() {
    const form = document.getElementById('editRoleForm');
    const formData = new FormData(form);
    
    // Get selected permissions
    const permissions = [];
    form.querySelectorAll('input[name="permissions[]"]:checked').forEach(cb => {
        permissions.push(cb.value);
    });
    
    formData.append('action', 'update_role');
    formData.append('permissions', JSON.stringify(permissions));
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editRoleModal')).hide();
            showAlert('success', data.message);
            loadRoles();
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Network error updating role');
    });
}

function updateRolePermissions() {
    const form = document.getElementById('rolePermissionsForm');
    const roleId = form.querySelector('[name="role_id"]').value;
    
    // Get selected permissions
    const permissions = [];
    form.querySelectorAll('input[name="role_permissions[]"]:checked').forEach(cb => {
        permissions.push(cb.value);
    });
    
    const formData = new FormData();
    formData.append('action', 'update_role_permissions');
    formData.append('role_id', roleId);
    formData.append('permissions', JSON.stringify(permissions));
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('rolePermissionsModal')).hide();
            showAlert('success', data.message);
            loadRoles();
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Network error updating permissions');
    });
}

function deleteRoleConfirm(roleId, roleName) {
    if (confirm(`Are you sure you want to delete the role "${roleName}"? This action cannot be undone.`)) {
        const formData = new FormData();
        formData.append('action', 'delete_role');
        formData.append('role_id', roleId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                loadRoles();
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Network error deleting role');
        });
    }
}

function refreshData() {
    loadRoles();
    loadPermissions();
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
</script>

<?php include '../../layouts/footer.php'; ?>
