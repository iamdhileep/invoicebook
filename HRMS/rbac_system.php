<?php
/**
 * 🔐 HRMS Role-Based Access Control Implementation
 * Comprehensive RBAC system for Admin, Manager, Employee roles
 */

require_once '../db.php';

class HRMSRoleManager {
    private $conn;
    private $current_user_id;
    private $current_user_role;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->initializeSession();
    }
    
    private function initializeSession() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user_id'])) {
            $this->current_user_id = $_SESSION['user_id'];
            $this->current_user_role = $this->getUserRole($_SESSION['user_id']);
        }
    }
    
    /**
     * Get user role from database
     */
    public function getUserRole($user_id) {
        $query = "SELECT r.role_name 
                  FROM users u 
                  JOIN user_roles ur ON u.id = ur.user_id 
                  JOIN roles r ON ur.role_id = r.id 
                  WHERE u.id = ?";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['role_name'];
        }
        
        return 'Employee'; // Default role
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission($permission_name) {
        if (!$this->current_user_id) {
            return false;
        }
        
        $query = "SELECT COUNT(*) as count 
                  FROM users u 
                  JOIN user_roles ur ON u.id = ur.user_id 
                  JOIN role_permissions rp ON ur.role_id = rp.role_id 
                  JOIN permissions p ON rp.permission_id = p.id 
                  WHERE u.id = ? AND p.permission_name = ?";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "is", $this->current_user_id, $permission_name);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['count'] > 0;
        }
        
        return false;
    }
    
    /**
     * Check if user can access specific module
     */
    public function canAccessModule($module_name) {
        $role_permissions = [
            'Admin' => ['*'], // All permissions
            'Manager' => [
                'view_employees', 'edit_employees', 'view_attendance', 
                'edit_attendance', 'view_reports', 'view_payroll',
                'manage_leave', 'view_performance'
            ],
            'Employee' => [
                'view_own_profile', 'edit_own_profile', 'view_own_attendance',
                'submit_leave_request', 'view_own_payroll', 'view_own_performance'
            ]
        ];
        
        if ($this->current_user_role === 'Admin') {
            return true; // Admin has access to everything
        }
        
        $allowed_modules = $role_permissions[$this->current_user_role] ?? [];
        return in_array($module_name, $allowed_modules);
    }
    
    /**
     * Restrict access based on user role
     */
    public function restrictAccess($required_role = null, $required_permission = null) {
        if (!$this->current_user_id) {
            $this->redirectToLogin();
            return false;
        }
        
        if ($required_role && $this->current_user_role !== $required_role && $this->current_user_role !== 'Admin') {
            $this->accessDenied();
            return false;
        }
        
        if ($required_permission && !$this->hasPermission($required_permission)) {
            $this->accessDenied();
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate role-based navigation menu
     */
    public function generateNavigation() {
        $navigation = [
            'Admin' => [
                'Dashboard' => 'dashboard.php',
                'Employee Management' => 'employee_directory.php',
                'Attendance Management' => 'attendance_management.php',
                'Leave Management' => 'leave_management.php',
                'Payroll Processing' => 'payroll_processing.php',
                'Performance Management' => 'performance_management.php',
                'Training Management' => 'training_management.php',
                'System Settings' => 'system_settings.php',
                'Audit Logs' => 'audit_logs.php'
            ],
            'Manager' => [
                'Dashboard' => 'dashboard.php',
                'Team Management' => 'team_management.php',
                'Attendance Review' => 'attendance_management.php',
                'Leave Approval' => 'leave_management.php',
                'Performance Reviews' => 'performance_management.php',
                'Team Reports' => 'reports.php'
            ],
            'Employee' => [
                'Dashboard' => 'dashboard.php',
                'My Profile' => 'employee_self_service.php',
                'My Attendance' => 'my_attendance.php',
                'Leave Requests' => 'my_leave_requests.php',
                'My Payroll' => 'my_payroll.php',
                'Training Programs' => 'my_training.php'
            ]
        ];
        
        return $navigation[$this->current_user_role] ?? $navigation['Employee'];
    }
    
    /**
     * Log user activity for audit trail
     */
    public function logActivity($action, $module, $details = '') {
        $query = "INSERT INTO audit_logs (user_id, action, module, details, ip_address, timestamp) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "issss", 
            $this->current_user_id, $action, $module, $details, $ip_address);
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Data access control - filter data based on user role
     */
    public function getAccessibleEmployees() {
        switch ($this->current_user_role) {
            case 'Admin':
                return "SELECT * FROM employees"; // All employees
                
            case 'Manager':
                // Get employees in same department or reporting to this manager
                return "SELECT e.* FROM employees e 
                        LEFT JOIN employees mgr ON mgr.id = " . $this->current_user_id . "
                        WHERE e.department_id = mgr.department_id 
                        OR e.manager_id = " . $this->current_user_id;
                
            case 'Employee':
                // Only own record
                return "SELECT * FROM employees WHERE user_id = " . $this->current_user_id;
                
            default:
                return "SELECT * FROM employees WHERE 1=0"; // No access
        }
    }
    
    private function redirectToLogin() {
        header('Location: ../authenticate.php');
        exit;
    }
    
    private function accessDenied() {
        http_response_code(403);
        echo "<h1>Access Denied</h1>";
        echo "<p>You don't have permission to access this resource.</p>";
        echo "<a href='dashboard.php'>Return to Dashboard</a>";
        exit;
    }
    
    /**
     * Get current user information
     */
    public function getCurrentUser() {
        return [
            'user_id' => $this->current_user_id,
            'role' => $this->current_user_role
        ];
    }
}

// Initialize RBAC system
$rbac = new HRMSRoleManager($conn);

// Example usage functions for easy integration

/**
 * Quick permission check function
 */
function checkPermission($permission) {
    global $rbac;
    return $rbac->hasPermission($permission);
}

/**
 * Quick access restriction function
 */
function requireRole($role) {
    global $rbac;
    return $rbac->restrictAccess($role);
}

/**
 * Quick access restriction by permission
 */
function requirePermission($permission) {
    global $rbac;
    return $rbac->restrictAccess(null, $permission);
}

/**
 * Log user action
 */
function logUserAction($action, $module, $details = '') {
    global $rbac;
    $rbac->logActivity($action, $module, $details);
}

/**
 * Generate secure navigation menu
 */
function getSecureNavigation() {
    global $rbac;
    return $rbac->generateNavigation();
}

/**
 * Get user-accessible employee query
 */
function getEmployeeAccessQuery() {
    global $rbac;
    return $rbac->getAccessibleEmployees();
}

echo "🔐 RBAC System Initialized Successfully!\n";
echo "✅ Role-based access control ready for implementation.\n";

?>
