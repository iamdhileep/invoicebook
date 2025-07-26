<?php
// Database setup for password reset functionality
include 'db.php';

// Create password_resets table
$sql = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
)";

if ($conn->query($sql) === TRUE) {
    echo "Password resets table created successfully or already exists.\n";
} else {
    echo "Error creating password resets table: " . $conn->error . "\n";
}

// Clean up expired tokens (run this periodically)
$cleanup_sql = "DELETE FROM password_resets WHERE expires_at < NOW()";
if ($conn->query($cleanup_sql) === TRUE) {
    echo "Expired tokens cleaned up.\n";
} else {
    echo "Error cleaning up expired tokens: " . $conn->error . "\n";
}

echo "Password reset system database setup completed.\n";
?>
