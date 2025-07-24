<?php
/**
 * Authentication Helper Functions
 * Provides utility functions for user authentication and authorization
 */

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has admin role
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if user has specific role
 * @param string $role
 * @return bool
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Require user to be logged in
 * Redirects to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Require user to be admin
 * Redirects to login page if not admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Require user to have specific role
 * @param string $role
 */
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current username
 * @return string|null
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

/**
 * Get current user role
 * @return string|null
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Update user's last login time
 * @param int $user_id
 */
function updateLastLogin($user_id) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}

/**
 * Get user information by ID
 * @param int $user_id
 * @return array|null
 */
function getUserById($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Check if username exists
 * @param string $username
 * @return bool
 */
function usernameExists($username) {
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/**
 * Check if email exists
 * @param string $email
 * @param int $exclude_user_id (optional) - exclude current user when updating
 * @return bool
 */
function emailExists($email, $exclude_user_id = null) {
    global $conn;
    if ($exclude_user_id) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $exclude_user_id);
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
    }
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/**
 * Create new user
 * @param string $username
 * @param string $email
 * @param string $password
 * @param string $full_name
 * @param string $role
 * @return bool
 */
function createUser($username, $email, $password, $full_name, $role = 'user') {
    global $conn;
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssss", $username, $email, $hashed_password, $full_name, $role);
    return $stmt->execute();
}

/**
 * Update user information
 * @param int $user_id
 * @param array $data
 * @return bool
 */
function updateUser($user_id, $data) {
    global $conn;
    
    $allowed_fields = ['email', 'full_name', 'phone', 'address', 'role'];
    $updates = [];
    $types = '';
    $values = [];
    
    foreach ($data as $field => $value) {
        if (in_array($field, $allowed_fields)) {
            $updates[] = "$field = ?";
            $types .= 's';
            $values[] = $value;
        }
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $values[] = $user_id;
    $types .= 'i';
    
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    
    return $stmt->execute();
}

/**
 * Update user password
 * @param int $user_id
 * @param string $new_password
 * @return bool
 */
function updateUserPassword($user_id, $new_password) {
    global $conn;
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    return $stmt->execute();
}

/**
 * Delete user
 * @param int $user_id
 * @return bool
 */
function deleteUser($user_id) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

/**
 * Verify user credentials
 * @param string $username
 * @param string $password
 * @return array|false User data if valid, false otherwise
 */
function verifyCredentials($username, $password) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            return $user;
        }
    }
    
    return false;
}

/**
 * Log user activity
 * @param int $user_id
 * @param string $action
 * @param string $details
 */
function logUserActivity($user_id, $action, $details = '') {
    global $conn;
    
    // Create activity_log table if it doesn't exist
    $create_table = "CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100),
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $conn->query($create_table);
    
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt->bind_param("issss", $user_id, $action, $details, $ip, $user_agent);
    $stmt->execute();
}
?> 