<?php
/**
 * Create hr_mobile_devices table
 * This script creates the missing hr_mobile_devices table that's required for the Mobile PWA Manager
 */

require_once 'db.php';

// SQL to create hr_mobile_devices table
$sql = "CREATE TABLE IF NOT EXISTS `hr_mobile_devices` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `device_info` text,
    `device_type` varchar(50) DEFAULT NULL,
    `browser_info` text,
    `push_token` text,
    `registered_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `last_active` timestamp NULL DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_last_active` (`last_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

try {
    if ($conn->query($sql) === TRUE) {
        echo "✅ Table 'hr_mobile_devices' created successfully or already exists.\n";
        
        // Check if the table has any data
        $result = $conn->query("SELECT COUNT(*) as count FROM hr_mobile_devices");
        $row = $result->fetch_assoc();
        echo "📊 Current records in hr_mobile_devices: " . $row['count'] . "\n";
        
    } else {
        echo "❌ Error creating table: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

$conn->close();
?>
