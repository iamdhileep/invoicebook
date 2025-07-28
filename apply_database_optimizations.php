<?php
/**
 * Apply Database Performance Indexes
 * This script will create the necessary indexes for optimal query performance
 */

include 'db.php';

echo "<h2>🚀 Applying Database Performance Optimizations</h2>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";

$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_attendance_employee_date ON attendance (employee_id, attendance_date)",
    "CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance (attendance_date)",
    "CREATE INDEX IF NOT EXISTS idx_attendance_employee ON attendance (employee_id)",
    "CREATE INDEX IF NOT EXISTS idx_employees_status ON employees (status)",
    "CREATE INDEX IF NOT EXISTS idx_employees_name ON employees (name)",
    "CREATE INDEX IF NOT EXISTS idx_employees_code ON employees (employee_code)",
    "CREATE INDEX IF NOT EXISTS idx_leave_status ON leave_requests (status)",
    "CREATE INDEX IF NOT EXISTS idx_leave_employee ON leave_requests (employee_id)",
    "CREATE INDEX IF NOT EXISTS idx_leave_dates ON leave_requests (start_date, end_date)",
    "CREATE INDEX IF NOT EXISTS idx_attendance_composite ON attendance (employee_id, attendance_date, punch_in_time)",
    "CREATE INDEX IF NOT EXISTS idx_leave_composite ON leave_requests (employee_id, status, start_date)"
];

$success_count = 0;
$total_count = count($indexes);

foreach ($indexes as $index_sql) {
    try {
        $result = $conn->query($index_sql);
        if ($result) {
            echo "✅ " . substr($index_sql, 0, 80) . "...<br>";
            $success_count++;
        } else {
            echo "❌ " . substr($index_sql, 0, 80) . "... ERROR: " . $conn->error . "<br>";
        }
    } catch (Exception $e) {
        echo "❌ " . substr($index_sql, 0, 80) . "... ERROR: " . $e->getMessage() . "<br>";
    }
}

echo "<hr>";
echo "<h3>📊 Summary</h3>";
echo "<p><strong>Indexes created:</strong> $success_count / $total_count</p>";

if ($success_count === $total_count) {
    echo "<div style='color: green; padding: 10px; border: 1px solid green; background: #f0f8f0;'>";
    echo "<strong>🎉 All database indexes created successfully!</strong><br>";
    echo "Your database is now optimized for faster query performance.";
    echo "</div>";
} else {
    echo "<div style='color: orange; padding: 10px; border: 1px solid orange; background: #fff8f0;'>";
    echo "<strong>⚠️ Some indexes may already exist or failed to create.</strong><br>";
    echo "This is usually normal - MySQL will skip existing indexes.";
    echo "</div>";
}

// Test query performance
echo "<hr>";
echo "<h3>⚡ Query Performance Test</h3>";

$start_time = microtime(true);
$result = $conn->query("SELECT COUNT(*) as total FROM employees WHERE status = 'active'");
$query_time = (microtime(true) - $start_time) * 1000;

echo "<p><strong>Sample query time:</strong> " . number_format($query_time, 2) . "ms</p>";

if ($query_time < 10) {
    echo "<p style='color: green;'>✅ Excellent performance! Queries are running very fast.</p>";
} else if ($query_time < 50) {
    echo "<p style='color: orange;'>⚠️ Good performance, but could be better with more data.</p>";
} else {
    echo "<p style='color: red;'>❌ Slow performance detected. Check database configuration.</p>";
}

echo "<hr>";
echo "<p><a href='verify_optimization.php'>🔍 Run Full Performance Verification</a></p>";
echo "<p><a href='pages/hr/hr_dashboard_new.php?perf=1'>🎯 Test HR Dashboard with Performance Monitor</a></p>";

$conn->close();
?>
