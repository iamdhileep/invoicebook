/* ✅ FILE: logout.php */
<?php
session_start();

include 'db.php';

// Log the logout if user was logged in
if (isset($_SESSION['admin'])) {
    $user_id = $_SESSION['admin'];
    
    // Log the logout action
    $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, ip_address, user_agent, created_at) VALUES (?, 'logout', ?, ?, NOW())");
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $stmt->bind_param('iss', $user_id, $ip, $user_agent);
    $stmt->execute();
    
    // Clear remember token if exists
    $stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
}

// Clear all session data
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>