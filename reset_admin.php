<?php
include 'db.php';

// Define new username and password
$username = 'admin';
$plainPassword = 'admin123';
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

// Update or Insert admin user
$stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)
    ON DUPLICATE KEY UPDATE password = VALUES(password)");

$stmt->bind_param("ss", $username, $hashedPassword);
if ($stmt->execute()) {
    echo "✅ Admin user reset.<br>Username: <b>admin</b><br>Password: <b>admin123</b>";
} else {
    echo "❌ Error: " . $stmt->error;
}
?>
