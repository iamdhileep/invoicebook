/* ✅ FILE: logout.php */
<?php
session_start();

// Simple and reliable logout without database dependencies
try {
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

    // Clear remember me cookie if it exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }

    // Destroy the session
    session_destroy();
    
} catch (Exception $e) {
    // Even if there's an error, we still want to log out
    error_log("Logout error: " . $e->getMessage());
}

// Redirect to login page
header("Location: login.php?logout=1");
exit;
?>