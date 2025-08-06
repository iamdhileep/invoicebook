<?php
// HRMS Configuration File
// Central configuration for the HR Management System

// Database Configuration
require_once dirname(__DIR__, 2) . '/db.php';

// Session Configuration
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// HRMS Constants
define('HRMS_VERSION', '1.0.0');
define('HRMS_NAME', 'Advanced HR Management System');
define('HRMS_PATH', dirname(__FILE__) . '/');
define('HRMS_URL', '/billbook/HRMS/');

// File Upload Configuration
define('HRMS_UPLOAD_PATH', '../uploads/hrms/');
define('HRMS_MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('HRMS_ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif']);

// Payroll Configuration
define('FINANCIAL_YEAR_START_MONTH', 4); // April
define('DEFAULT_CURRENCY', 'INR');
define('DEFAULT_COUNTRY', 'India');

// Attendance Configuration
define('DEFAULT_GRACE_TIME_MINUTES', 15);
define('DEFAULT_OVERTIME_RATE', 1.5);
define('AUTO_CLOCK_OUT_ENABLED', true);

// Security Configuration
define('HRMS_ENCRYPTION_KEY', 'your-secret-encryption-key-here');
define('PASSWORD_MIN_LENGTH', 8);
define('SESSION_TIMEOUT_MINUTES', 30);

// Email Configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('FROM_EMAIL', 'noreply@billbook.com');
define('FROM_NAME', 'BillBook HRMS');

// Role-based Access Control
$HRMS_ROLES = [
    'admin' => [
        'name' => 'System Administrator',
        'permissions' => ['all']
    ],
    'hr' => [
        'name' => 'HR Manager',
        'permissions' => [
            'hr_management',        // Added for hr_panel access
            'employee',             // Added for employee_panel access
            'employee_management',
            'attendance_management',
            'leave_management',
            'payroll_management',
            'onboarding',
            'offboarding',
            'reports',
            'settings'
        ]
    ],
    'manager' => [
        'name' => 'Department Manager',
        'permissions' => [
            'manager',              // Added for employee_panel access
            'team_view',
            'attendance_view',
            'leave_approval',
            'performance_management',
            'team_reports'
        ]
    ],
    'employee' => [
        'name' => 'Employee',
        'permissions' => [
            'employee',             // Added for employee_panel access
            'self_attendance',
            'leave_application',
            'payslip_view',
            'profile_edit',
            'document_upload'
        ]
    ]
];

// HRMS Helper Functions
class HRMSHelper {
    
    /**
     * Check if user has permission
     */
    public static function hasPermission($permission, $userRole = null) {
        global $HRMS_ROLES;
        
        if (!$userRole) {
            // Use the getCurrentUserRole method for consistency
            $userRole = self::getCurrentUserRole();
        }
        
        if (!isset($HRMS_ROLES[$userRole])) {
            return false;
        }
        
        $userPermissions = $HRMS_ROLES[$userRole]['permissions'];
        
        // Admin has all permissions
        if (in_array('all', $userPermissions)) {
            return true;
        }
        
        return in_array($permission, $userPermissions);
    }
    
    /**
     * Get current user role
     */
    public static function getCurrentUserRole() {
        // Check both possible session variables for compatibility
        return $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'employee';
    }
    
    /**
     * Get current user ID
     */
    public static function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Redirect if not logged in
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ../hrms_portal.php');
            exit;
        }
    }
    
    /**
     * Redirect if no permission
     */
    public static function requirePermission($permission) {
        self::requireLogin();
        
        if (!self::hasPermission($permission)) {
            header('Location: access_denied.php');
            exit;
        }
    }
    
    /**
     * Format currency
     */
    public static function formatCurrency($amount, $currency = DEFAULT_CURRENCY) {
        return $currency . ' ' . number_format($amount, 2);
    }
    
    /**
     * Format date
     */
    public static function formatDate($date, $format = 'Y-m-d') {
        if (empty($date) || $date === '0000-00-00') {
            return '';
        }
        
        return date($format, strtotime($date));
    }
    
    /**
     * Format time
     */
    public static function formatTime($time, $format = 'H:i') {
        if (empty($time)) {
            return '';
        }
        
        return date($format, strtotime($time));
    }
    
    /**
     * Calculate working days between two dates
     */
    public static function calculateWorkingDays($startDate, $endDate, $excludeWeekends = true) {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $end->modify('+1 day'); // Include end date
        
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);
        
        $workingDays = 0;
        foreach ($period as $date) {
            if ($excludeWeekends) {
                $dayOfWeek = $date->format('N'); // 1 (Monday) to 7 (Sunday)
                if ($dayOfWeek < 6) { // Monday to Friday
                    $workingDays++;
                }
            } else {
                $workingDays++;
            }
        }
        
        return $workingDays;
    }
    
    /**
     * Generate employee ID
     */
    public static function generateEmployeeId($prefix = 'EMP') {
        global $conn;
        
        $year = date('Y');
        $query = "SELECT COUNT(*) as count FROM hr_employees WHERE employee_id LIKE '$prefix$year%'";
        $result = $conn->query($query);
        $row = $result->fetch_assoc();
        $count = $row['count'] + 1;
        
        return $prefix . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Log audit trail
     */
    public static function logAudit($action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
        global $conn;
        
        $userId = self::getCurrentUserId();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $oldValuesJson = $oldValues ? json_encode($oldValues) : null;
        $newValuesJson = $newValues ? json_encode($newValues) : null;
        
        $stmt = $conn->prepare("
            INSERT INTO hr_audit_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("ississss", $userId, $action, $tableName, $recordId, $oldValuesJson, $newValuesJson, $ipAddress, $userAgent);
        $stmt->execute();
    }
    
    /**
     * Send notification (email/system)
     */
    public static function sendNotification($to, $subject, $message, $type = 'email') {
        // Implementation for sending notifications
        // This can be extended to support email, SMS, push notifications, etc.
        
        if ($type === 'email') {
            // Email implementation using PHPMailer or similar
            // For now, just log it
            error_log("HRMS Notification: To: $to, Subject: $subject, Message: $message");
        }
        
        return true;
    }
    
    /**
     * Upload file with validation
     */
    public static function uploadFile($file, $uploadDir = HRMS_UPLOAD_PATH, $allowedTypes = HRMS_ALLOWED_FILE_TYPES) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }
        
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileTmpName = $file['tmp_name'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($fileType, $allowedTypes)) {
            return ['success' => false, 'message' => 'File type not allowed'];
        }
        
        // Validate file size
        if ($fileSize > HRMS_MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File size too large'];
        }
        
        // Generate unique filename
        $newFileName = uniqid() . '_' . time() . '.' . $fileType;
        $uploadPath = $uploadDir . $newFileName;
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Move uploaded file
        if (move_uploaded_file($fileTmpName, $uploadPath)) {
            return [
                'success' => true,
                'message' => 'File uploaded successfully',
                'filename' => $newFileName,
                'filepath' => $uploadPath,
                'size' => $fileSize,
                'type' => $fileType
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to upload file'];
        }
    }
    
    /**
     * Generate secure password
     */
    public static function generatePassword($length = 12) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Hash password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Get financial year
     */
    public static function getFinancialYear($date = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $year = date('Y', strtotime($date));
        $month = date('n', strtotime($date));
        
        if ($month < FINANCIAL_YEAR_START_MONTH) {
            return ($year - 1) . '-' . $year;
        } else {
            return $year . '-' . ($year + 1);
        }
    }
    
    /**
     * Get age from date of birth
     */
    public static function calculateAge($dateOfBirth) {
        if (empty($dateOfBirth) || $dateOfBirth === '0000-00-00') {
            return 0;
        }
        
        $today = new DateTime();
        $dob = new DateTime($dateOfBirth);
        $age = $today->diff($dob);
        
        return $age->y;
    }
    
    /**
     * Get employee experience
     */
    public static function calculateExperience($joiningDate) {
        if (empty($joiningDate) || $joiningDate === '0000-00-00') {
            return '0 years';
        }
        
        $today = new DateTime();
        $joining = new DateTime($joiningDate);
        $experience = $today->diff($joining);
        
        $years = $experience->y;
        $months = $experience->m;
        
        if ($years > 0 && $months > 0) {
            return "$years years, $months months";
        } elseif ($years > 0) {
            return "$years years";
        } else {
            return "$months months";
        }
    }
    
    /**
     * Sanitize input
     */
    public static function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate phone number
     */
    public static function validatePhone($phone) {
        return preg_match('/^[0-9]{10,15}$/', $phone);
    }
    
    /**
     * Get random color for charts/avatars
     */
    public static function getRandomColor() {
        $colors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
            '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF',
            '#4BC0C0', '#FF6384', '#36A2EB', '#FFCE56'
        ];
        
        return $colors[array_rand($colors)];
    }
    
    /**
     * Format time ago (relative time)
     */
    public static function timeAgo($datetime) {
        if (empty($datetime)) {
            return '';
        }
        
        $time = time() - strtotime($datetime);
        
        if ($time < 60) {
            return 'just now';
        } elseif ($time < 3600) {
            $minutes = floor($time / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($time < 86400) {
            $hours = floor($time / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($time < 2592000) {
            $days = floor($time / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', strtotime($datetime));
        }
    }
    
    /**
     * Safe database query with error handling
     */
    public static function safeQuery($query, $defaultValue = null) {
        global $conn;
        
        if (!isset($conn) || $conn === null) {
            return $defaultValue;
        }
        
        try {
            $result = $conn->query($query);
            if ($result === false) {
                error_log("HRMS Query Error: " . $conn->error . " | Query: " . $query);
                return $defaultValue;
            }
            return $result;
        } catch (Exception $e) {
            error_log("HRMS Query Exception: " . $e->getMessage() . " | Query: " . $query);
            return $defaultValue;
        }
    }
}

// Initialize HRMS
function initializeHRMS() {
    // Check if database tables exist
    global $conn;
    
    // Ensure connection exists
    if (!isset($conn) || $conn === null) {
        return false;
    }
    
    // Check if HRMS is already initialized (cache check)
    if (isset($_SESSION['hrms_initialized']) && $_SESSION['hrms_initialized'] === true) {
        return true;
    }
    
    // Quick check - only verify one core table exists
    $result = $conn->query("SHOW TABLES LIKE 'hr_employees'");
    if ($result && $result->num_rows > 0) {
        // Tables exist, mark as initialized
        $_SESSION['hrms_initialized'] = true;
        return true;
    }
    
    // If core table doesn't exist, check all required tables
    $tables = [
        'hr_departments', 'hr_designations', 'hr_employees',
        'hr_attendance', 'hr_leave_types', 'hr_leave_applications',
        'hr_payroll', 'hr_salary_components'
    ];
    
    $missingTables = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if (!$result || $result->num_rows === 0) {
            $missingTables[] = $table;
        }
    }
    
    if (!empty($missingTables)) {
        // Auto-create tables if they don't exist (only once)
        $sqlFile = HRMS_PATH . 'hrms_database_schema.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            
            // Split and execute statements individually for better performance
            $statements = explode(';', $sql);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $conn->query($statement);
                }
            }
        }
    }
    
    // Mark as initialized to avoid running again
    $_SESSION['hrms_initialized'] = true;
    return true;
}

// Auto-initialize HRMS when this file is included (only if connection exists and not already initialized)
if (isset($conn) && $conn !== null && (!isset($_SESSION['hrms_initialized']) || $_SESSION['hrms_initialized'] !== true)) {
    initializeHRMS();
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting for development (remove in production)
// ini_set('display_errors', 1);
// error_reporting(E_ALL);
?>
