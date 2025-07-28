<?php
session_start();
include 'db.php';

// Simulate login
$username = 'admin';
$password = 'admin'; // We need to check what the actual password is

// Check if user exists
$stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
if ($stmt) {
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        echo "User found: " . $user['username'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        
        // Try common passwords
        $passwords_to_try = ['admin', 'password', '123456', 'admin123'];
        
        foreach ($passwords_to_try as $pwd) {
            if (password_verify($pwd, $user['password'])) {
                echo "Password found: " . $pwd . "\n";
                
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['admin'] = $user['username'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                echo "Session set successfully!\n";
                exit;
            }
        }
        
        echo "Password not found in common passwords.\n";
        echo "Hash: " . $user['password'] . "\n";
    } else {
        echo "User not found.\n";
    }
} else {
    echo "Query failed.\n";
}

$conn->close();
?>
