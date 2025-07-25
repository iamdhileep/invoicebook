<?php
// User Permission Model
class UserPermission {
    private $conn;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    // Check if user has specific permission
    public function hasPermission($user_id, $permission) {
        $stmt = $this->conn->prepare("SELECT permissions FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result) {
            return false;
        }
        
        $user_permissions = explode(',', $result['permissions']);
        return in_array($permission, $user_permissions) || in_array('admin', $user_permissions);
    }
    
    // Check if user has admin role
    public function isAdmin($user_id) {
        $stmt = $this->conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result && $result['role'] === 'admin';
    }
    
    // Get user permissions
    public function getUserPermissions($user_id) {
        $stmt = $this->conn->prepare("SELECT permissions, role FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result) {
            return [];
        }
        
        $permissions = explode(',', $result['permissions']);
        
        // If admin, grant all permissions
        if ($result['role'] === 'admin') {
            return [
                'dashboard', 'employees', 'attendance', 'invoices', 
                'items', 'reports', 'settings', 'export', 'bulk_actions'
            ];
        }
        
        return array_filter($permissions);
    }
    
    // Add permission to user
    public function addPermission($user_id, $permission) {
        $current_permissions = $this->getUserPermissions($user_id);
        
        if (!in_array($permission, $current_permissions)) {
            $current_permissions[] = $permission;
            $permissions_string = implode(',', $current_permissions);
            
            $stmt = $this->conn->prepare("UPDATE users SET permissions = ? WHERE id = ?");
            $stmt->bind_param('si', $permissions_string, $user_id);
            return $stmt->execute();
        }
        
        return true;
    }
    
    // Remove permission from user
    public function removePermission($user_id, $permission) {
        $current_permissions = $this->getUserPermissions($user_id);
        $current_permissions = array_filter($current_permissions, function($p) use ($permission) {
            return $p !== $permission;
        });
        
        $permissions_string = implode(',', $current_permissions);
        
        $stmt = $this->conn->prepare("UPDATE users SET permissions = ? WHERE id = ?");
        $stmt->bind_param('si', $permissions_string, $user_id);
        return $stmt->execute();
    }
    
    // Validate user session and permissions
    public function validateAccess($required_permission = null) {
        if (!isset($_SESSION['admin'])) {
            return false;
        }
        
        $user_id = $_SESSION['admin'];
        
        // Check if user exists
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if (!$user) {
            return false;
        }
        
        // If no specific permission required, just check if user exists
        if (!$required_permission) {
            return true;
        }
        
        // Check specific permission
        return $this->hasPermission($user_id, $required_permission);
    }
    
    // Get user info
    public function getUserInfo($user_id) {
        $stmt = $this->conn->prepare("SELECT id, username, email, role, permissions, created_at FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    // Create new user
    public function createUser($username, $email, $password, $role = 'user', $permissions = []) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $permissions_string = implode(',', $permissions);
        
        $stmt = $this->conn->prepare("INSERT INTO users (username, email, password, role, permissions, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('sssss', $username, $email, $hashed_password, $role, $permissions_string);
        
        return $stmt->execute();
    }
    
    // Update user
    public function updateUser($user_id, $username, $email, $role, $permissions = [], $password = null) {
        $permissions_string = implode(',', $permissions);
        
        if ($password) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ?, permissions = ? WHERE id = ?");
            $stmt->bind_param('sssssi', $username, $email, $hashed_password, $role, $permissions_string, $user_id);
        } else {
            $stmt = $this->conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, permissions = ? WHERE id = ?");
            $stmt->bind_param('ssssi', $username, $email, $role, $permissions_string, $user_id);
        }
        
        return $stmt->execute();
    }
    
    // Delete user
    public function deleteUser($user_id) {
        $stmt = $this->conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        return $stmt->execute();
    }
    
    // Authenticate user
    public function authenticate($username, $password) {
        $stmt = $this->conn->prepare("SELECT id, password FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result && password_verify($password, $result['password'])) {
            return $result['id'];
        }
        
        return false;
    }
}

// Permission constants
class Permissions {
    const DASHBOARD = 'dashboard';
    const EMPLOYEES = 'employees';
    const ATTENDANCE = 'attendance';
    const INVOICES = 'invoices';
    const ITEMS = 'items';
    const REPORTS = 'reports';
    const SETTINGS = 'settings';
    const EXPORT = 'export';
    const BULK_ACTIONS = 'bulk_actions';
    
    public static function getAll() {
        return [
            self::DASHBOARD => 'Dashboard Access',
            self::EMPLOYEES => 'Employee Management',
            self::ATTENDANCE => 'Attendance Management',
            self::INVOICES => 'Invoice Management',
            self::ITEMS => 'Item Management',
            self::REPORTS => 'Reports Access',
            self::SETTINGS => 'Settings Access',
            self::EXPORT => 'Export Data',
            self::BULK_ACTIONS => 'Bulk Actions'
        ];
    }
}

// Helper functions
function requirePermission($permission) {
    global $conn;
    $userPermission = new UserPermission($conn);
    
    if (!$userPermission->validateAccess($permission)) {
        header('Location: login.php');
        exit;
    }
}

function hasPermission($permission) {
    if (!isset($_SESSION['admin'])) {
        return false;
    }
    
    global $conn;
    $userPermission = new UserPermission($conn);
    return $userPermission->hasPermission($_SESSION['admin'], $permission);
}

function getCurrentUser() {
    if (!isset($_SESSION['admin'])) {
        return null;
    }
    
    global $conn;
    $userPermission = new UserPermission($conn);
    return $userPermission->getUserInfo($_SESSION['admin']);
}
?>
