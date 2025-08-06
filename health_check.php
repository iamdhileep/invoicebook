<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Complete System Health Check</h1>";

// 1. Database Connection Test
echo "<h2>1. Database Connection</h2>";
try {
    include 'db.php';
    if ($conn) {
        echo "✅ Database connected successfully<br>";
        echo "✅ Connection charset: " . $conn->character_set_name() . "<br>";
    } else {
        echo "❌ Database connection failed<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// 2. Critical Tables Check
echo "<h2>2. Critical Tables</h2>";
$critical_tables = ['users', 'user_permissions', 'permission_groups', 'hrms_pages'];
foreach ($critical_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
        $count = $count_result->fetch_assoc()['count'];
        echo "✅ Table '$table' exists with $count records<br>";
    } else {
        echo "❌ Table '$table' missing<br>";
    }
}

// 3. Admin User Check
echo "<h2>3. Admin User Status</h2>";
$result = $conn->query("SELECT id, username, role FROM users WHERE role = 'admin' OR username = 'admin'");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "✅ Admin user found: ID={$row['id']}, Username={$row['username']}, Role={$row['role']}<br>";
    }
} else {
    echo "❌ No admin users found<br>";
}

// 4. Permission System Test
echo "<h2>4. Permission System</h2>";
try {
    require_once 'models/UserPermission.php';
    $userPerm = new UserPermission($conn);
    echo "✅ UserPermission class loaded<br>";
    
    // Test dashboard permission
    $hasPerm = $userPerm->hasGroupPermission(1, 'Dashboard');
    echo $hasPerm ? "✅ Admin has Dashboard permission<br>" : "❌ Admin lacks Dashboard permission<br>";
    
} catch (Exception $e) {
    echo "❌ Permission system error: " . $e->getMessage() . "<br>";
}

// 5. File Structure Check
echo "<h2>5. Critical Files</h2>";
$critical_files = [
    'config.php',
    'db.php',
    'auth_guard.php',
    'login.php',
    'dashboard.php',
    'pages/dashboard/dashboard.php',
    'models/UserPermission.php'
];

foreach ($critical_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file missing<br>";
    }
}

// 6. Session Test
echo "<h2>6. Session Functionality</h2>";
session_start();
$_SESSION['test'] = 'working';
if (isset($_SESSION['test'])) {
    echo "✅ Sessions are working<br>";
    unset($_SESSION['test']);
} else {
    echo "❌ Session issues detected<br>";
}

// 7. Login Flow Test
echo "<h2>7. Quick Tests</h2>";
echo "<a href='login.php' target='_blank'>🔑 Test Login Page</a><br>";
echo "<a href='final_test.php' target='_blank'>🏠 Test Dashboard Access</a><br>";
echo "<a href='password_reset.php' target='_blank'>🔧 Password Reset Tool</a><br>";

echo "<hr>";
echo "<h2>✨ System Status Summary</h2>";
echo "<p>If all items above show ✅, your login system should be working perfectly!</p>";
echo "<p>If you see any ❌, those items need attention.</p>";
?>
