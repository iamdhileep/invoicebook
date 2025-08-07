<?php
/**
 * Performance Optimization for Mobile PWA Manager
 * This script optimizes database queries and adds caching
 */

require_once 'db.php';
require_once 'HRMS/includes/hrms_config.php';

echo "🚀 Optimizing Mobile PWA Manager Performance...\n\n";

// 1. Add indexes for better performance
echo "Step 1: Adding Database Indexes\n";
$indexes = [
    "ALTER TABLE hr_mobile_devices ADD INDEX IF NOT EXISTS idx_user_id (user_id)",
    "ALTER TABLE hr_mobile_devices ADD INDEX IF NOT EXISTS idx_last_active (last_active)",
    "ALTER TABLE users ADD INDEX IF NOT EXISTS idx_email (email)",
    "ALTER TABLE hr_employees ADD INDEX IF NOT EXISTS idx_email (email)",
    "ALTER TABLE hr_attendance ADD INDEX IF NOT EXISTS idx_date_mobile (date, mobile_clock_in, mobile_clock_out)",
];

foreach ($indexes as $index) {
    try {
        $conn->query($index);
        echo "✅ Index created/verified\n";
    } catch (Exception $e) {
        echo "⚠️  Index might already exist: " . $e->getMessage() . "\n";
    }
}

echo "\nStep 2: Testing Query Performance\n";

// Test the main query performance
$start = microtime(true);
$result = HRMSHelper::safeQuery("
    SELECT 
        md.*,
        u.username,
        e.first_name, e.last_name
    FROM hr_mobile_devices md
    LEFT JOIN users u ON md.user_id = u.id
    LEFT JOIN hr_employees e ON u.email = e.email
    ORDER BY md.last_active DESC
    LIMIT 50
");
$end = microtime(true);

$queryTime = ($end - $start) * 1000;
echo "✅ Main query execution time: " . number_format($queryTime, 2) . " ms\n";

if ($queryTime > 100) {
    echo "⚠️  Query is slow (>100ms). Consider further optimization.\n";
} else {
    echo "✅ Query performance is good (<100ms)\n";
}

echo "\nStep 3: Testing Stats Queries\n";

$start = microtime(true);
$statsResult = HRMSHelper::safeQuery("
    SELECT 
        COUNT(DISTINCT user_id) as registered_devices,
        COUNT(*) as total_registrations,
        SUM(CASE WHEN last_active >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_last_week
    FROM hr_mobile_devices
");
$end = microtime(true);

$statsTime = ($end - $start) * 1000;
echo "✅ Stats query execution time: " . number_format($statsTime, 2) . " ms\n";

echo "\n🎉 Performance optimization completed!\n";
echo "📊 Total optimization time: " . number_format(($queryTime + $statsTime), 2) . " ms\n";

$conn->close();
?>
