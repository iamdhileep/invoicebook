<?php
include 'db.php';

echo "<h2>Password Reset Tool</h2>";

// Check current admin password
$result = $conn->query("SELECT username, password FROM users WHERE username = 'admin'");
if ($result && $row = $result->fetch_assoc()) {
    echo "Current admin user found:<br>";
    echo "Username: " . $row['username'] . "<br>";
    echo "Password hash: " . substr($row['password'], 0, 20) . "...<br><br>";
    
    // Check if it's already a bcrypt hash
    if (password_get_info($row['password'])['algo'] !== null) {
        echo "✅ Password appears to be properly hashed<br>";
        
        // Test some common passwords
        $common_passwords = ['admin', 'password', '123456', 'admin123', ''];
        echo "<h3>Testing common passwords:</h3>";
        foreach ($common_passwords as $test_pass) {
            if (password_verify($test_pass, $row['password'])) {
                echo "✅ Password is: '" . $test_pass . "'<br>";
                break;
            } else {
                echo "❌ Not: '" . $test_pass . "'<br>";
            }
        }
    } else {
        echo "⚠️ Password might not be properly hashed<br>";
    }
} else {
    echo "❌ Admin user not found<br>";
}

echo "<hr>";
echo "<h3>Reset Admin Password to 'admin123'</h3>";
if (isset($_GET['reset'])) {
    $new_password = 'admin123';
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->bind_param("s", $hashed);
    
    if ($stmt->execute()) {
        echo "✅ Password reset successfully to: " . $new_password . "<br>";
        echo "<a href='test_login_form.php'>Test Login Now</a>";
    } else {
        echo "❌ Failed to reset password<br>";
    }
} else {
    echo "<a href='?reset=1'>Click to Reset Admin Password</a>";
}
?>
