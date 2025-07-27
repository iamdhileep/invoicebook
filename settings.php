<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

// Check if users table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check->num_rows === 0) {
    // Users table doesn't exist, show setup message
    $setup_needed = true;
} else {
    $setup_needed = false;
    
    // Check if user has permission to access settings
    $user_id = $_SESSION['admin'];
    
    // First check if permissions column exists
    $columns_check = $conn->query("SHOW COLUMNS FROM users LIKE 'permissions'");
    $has_permissions_column = $columns_check->num_rows > 0;
    
    if ($has_permissions_column) {
        // Use the new permission system
        $permission_check = $conn->prepare("SELECT * FROM users WHERE id = ? AND (role = 'admin' OR permissions LIKE '%settings%')");
    } else {
        // Fallback to simple role check or assume admin access
        $permission_check = $conn->prepare("SELECT * FROM users WHERE id = ?");
    }
    
    if ($permission_check === false) {
        die("SQL Error: " . $conn->error);
    }
    
    $permission_check->bind_param('i', $user_id);
    $permission_check->execute();
    $user = $permission_check->get_result()->fetch_assoc();
    
    // If no user found, check if this is a legacy admin session
    if (!$user && isset($_SESSION['admin'])) {
        // For backward compatibility, assume admin access if session exists
        $user = ['id' => $_SESSION['admin'], 'username' => 'admin', 'role' => 'admin', 'permissions' => 'all'];
    }
}

// If setup is needed, show setup page
if ($setup_needed) {
    // Run the database setup automatically
    $setup_sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(100) UNIQUE NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'manager', 'employee', 'viewer') DEFAULT 'employee',
        permissions TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        failed_login_attempts INT DEFAULT 0,
        locked_until TIMESTAMP NULL
    );

    CREATE TABLE IF NOT EXISTS user_activity_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        action VARCHAR(100),
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    -- Insert default admin user
    INSERT IGNORE INTO users (username, email, password, role, permissions) VALUES 
    ('admin', 'admin@billbook.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'all'),
    ('manager', 'manager@billbook.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'attendance,reports,employees'),
    ('user', 'user@billbook.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 'attendance');
    ";
    
    // Execute the setup SQL
    if ($conn->multi_query($setup_sql)) {
        // Consume all results
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        
        $setup_success = "Database setup completed successfully! Default users created with password 'password'";
        
        // Now check permissions again
        $user_id = $_SESSION['admin'];
        $permission_check = $conn->prepare("SELECT * FROM users WHERE id = ? AND (role = 'admin' OR permissions LIKE '%settings%')");
        
        if ($permission_check !== false) {
            $permission_check->bind_param('i', $user_id);
            $permission_check->execute();
            $user = $permission_check->get_result()->fetch_assoc();
        } else {
            // Assume admin access for now
            $user = ['username' => 'admin', 'role' => 'admin'];
        }
    } else {
        $setup_error = "Error setting up database: " . $conn->error;
        $user = ['username' => 'admin', 'role' => 'admin']; // Allow access to show error
    }
} else {
    // Table exists, check permissions normally
    if (!$user) {
        header('Location: dashboard.php');
        exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_user') {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $permissions = implode(',', $_POST['permissions'] ?? []);
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, permissions, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('sssss', $username, $email, $password, $role, $permissions);
        
        if ($stmt->execute()) {
            $success = "User added successfully!";
        } else {
            $error = "Error adding user: " . $conn->error;
        }
    }
    
    if ($action === 'update_user') {
        $user_id = $_POST['user_id'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $permissions = implode(',', $_POST['permissions'] ?? []);
        
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ?, permissions = ? WHERE id = ?");
            $stmt->bind_param('sssssi', $username, $email, $password, $role, $permissions, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, permissions = ? WHERE id = ?");
            $stmt->bind_param('ssssi', $username, $email, $role, $permissions, $user_id);
        }
        
        if ($stmt->execute()) {
            $success = "User updated successfully!";
        } else {
            $error = "Error updating user: " . $conn->error;
        }
    }
    
    if ($action === 'delete_user') {
        $user_id = $_POST['user_id'];
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND id != ?");
        $stmt->bind_param('ii', $user_id, $_SESSION['admin']);
        
        if ($stmt->execute()) {
            $success = "User deleted successfully!";
        } else {
            $error = "Error deleting user: " . $conn->error;
        }
    }
}

// Get all users
$users_query = "SELECT * FROM users ORDER BY created_at DESC";
$users_result = $conn->query($users_query);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

// Available permissions
$available_permissions = [
    'dashboard' => 'Dashboard Access',
    'employees' => 'Employee Management',
    'attendance' => 'Attendance Management',
    'invoices' => 'Invoice Management',
    'items' => 'Item Management',
    'reports' => 'Reports Access',
    'settings' => 'Settings Access',
    'export' => 'Export Data',
    'bulk_actions' => 'Bulk Actions'
];

include 'layouts/header.php';
?>

<div class="main-content">
    <?php include 'layouts/sidebar.php'; ?>
    
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <!-- Page Header -->
                    <div class="page-header d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="page-title mb-0">
                                <i class="bi bi-gear"></i>
                                Settings & User Management
                            </h4>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Settings</li>
                                </ol>
                            </nav>
                        </div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="bi bi-person-plus"></i> Add New User
                        </button>
                    </div>

                    <!-- Alert Messages -->
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- User Management Section -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-people me-2"></i>
                                User Management
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="usersTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Permissions</th>
                                            <th>Created</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user_row): ?>
                                            <tr>
                                                <td><?= $user_row['id'] ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($user_row['username']) ?></strong>
                                                    <?php if ($user_row['id'] == $_SESSION['admin']): ?>
                                                        <span class="badge bg-info ms-1">You</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($user_row['email']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $user_row['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                                        <?= ucfirst($user_row['role']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $user_permissions = explode(',', $user_row['permissions']);
                                                    foreach ($user_permissions as $perm):
                                                        if (!empty($perm)):
                                                    ?>
                                                        <span class="badge bg-secondary me-1"><?= $perm ?></span>
                                                    <?php 
                                                        endif;
                                                    endforeach; 
                                                    ?>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($user_row['created_at'])) ?></td>
                                                <td>
                                                    <span class="badge bg-success">Active</span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="editUser(<?= $user_row['id'] ?>)" 
                                                                data-bs-toggle="tooltip" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <?php if ($user_row['id'] != $_SESSION['admin']): ?>
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    onclick="deleteUser(<?= $user_row['id'] ?>, '<?= htmlspecialchars($user_row['username']) ?>')" 
                                                                    data-bs-toggle="tooltip" title="Delete">
                                                                <i class="bi bi-trash"></i>
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

                    <!-- System Settings Section -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-sliders me-2"></i>
                                System Settings
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Available Permissions</h6>
                                    <div class="list-group">
                                        <?php foreach ($available_permissions as $key => $label): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= $label ?></strong>
                                                    <br><small class="text-muted">Permission: <?= $key ?></small>
                                                </div>
                                                <span class="badge bg-info"><?= $key ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>System Information</h6>
                                    <div class="list-group">
                                        <div class="list-group-item">
                                            <strong>Total Users:</strong> <?= count($users) ?>
                                        </div>
                                        <div class="list-group-item">
                                            <strong>Admin Users:</strong> 
                                            <?= count(array_filter($users, function($u) { return $u['role'] === 'admin'; })) ?>
                                        </div>
                                        <div class="list-group-item">
                                            <strong>Regular Users:</strong> 
                                            <?= count(array_filter($users, function($u) { return $u['role'] !== 'admin'; })) ?>
                                        </div>
                                        <div class="list-group-item">
                                            <strong>Current User:</strong> <?= htmlspecialchars($user['username'] ?? 'Admin User') ?>
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role *</label>
                            <select name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="user">User</option>
                                <option value="manager">Manager</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Permissions</label>
                            <div class="row">
                                <?php foreach ($available_permissions as $key => $label): ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input type="checkbox" name="permissions[]" value="<?= $key ?>" 
                                                   class="form-check-input" id="perm_<?= $key ?>">
                                            <label class="form-check-label" for="perm_<?= $key ?>">
                                                <?= $label ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password (leave blank to keep current)</label>
                            <input type="password" name="password" id="edit_password" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role *</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="admin">Admin</option>
                                <option value="user">User</option>
                                <option value="manager">Manager</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Permissions</label>
                            <div class="row" id="edit_permissions_container">
                                <?php foreach ($available_permissions as $key => $label): ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input type="checkbox" name="permissions[]" value="<?= $key ?>" 
                                                   class="form-check-input" id="edit_perm_<?= $key ?>">
                                            <label class="form-check-label" for="edit_perm_<?= $key ?>">
                                                <?= $label ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Form -->
<form method="POST" id="deleteUserForm" style="display: none;">
    <input type="hidden" name="action" value="delete_user">
    <input type="hidden" name="user_id" id="delete_user_id">
</form>

<?php include 'layouts/footer.php'; ?>

<script>
// Users data for JavaScript
const usersData = <?= json_encode($users) ?>;

// Edit user function
function editUser(userId) {
    const user = usersData.find(u => u.id == userId);
    if (!user) return;
    
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_password').value = '';
    
    // Clear all permission checkboxes first
    document.querySelectorAll('#edit_permissions_container input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
    
    // Check user's permissions
    if (user.permissions) {
        const userPermissions = user.permissions.split(',');
        userPermissions.forEach(perm => {
            const checkbox = document.getElementById(`edit_perm_${perm.trim()}`);
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    }
    
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

// Delete user function
function deleteUser(userId, username) {
    if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('deleteUserForm').submit();
    }
}

// Initialize DataTable
document.addEventListener('DOMContentLoaded', function() {
    if ($.fn.DataTable) {
        $('#usersTable').DataTable({
            responsive: true,
            order: [[0, 'desc']]
        });
    }
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<style>
.badge {
    font-size: 0.75rem;
}

.list-group-item {
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.form-check-label {
    font-size: 0.9rem;
}

.table th {
    font-weight: 600;
    font-size: 0.875rem;
}

.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}
</style>
