<?php
include 'db.php';

// Only run once to insert admin user
$username = 'admin';
$password = 'admin123';
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Check if user exists
$stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashedPassword);
    $stmt->execute();
    echo "✅ Admin user created successfully.";
} else {
    echo "⚠️ Admin user already exists.";
}
?>
