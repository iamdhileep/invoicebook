<?php
session_start();
include 'db.php';

// Login with admin credentials
$username = 'admin';
$password = 'admin123';

$stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
if ($stmt) {
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['admin'] = $user['username'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            echo "Login successful! Session established.\n";
            echo "User ID: " . $_SESSION['user_id'] . "\n";
            echo "Username: " . $_SESSION['username'] . "\n";
            echo "Role: " . $_SESSION['role'] . "\n";
            
            // Redirect to test page
            header("Location: api_test.html");
            exit;
        } else {
            echo "Invalid password.\n";
        }
    } else {
        echo "User not found.\n";
    }
} else {
    echo "Query failed.\n";
}

$conn->close();
?>
