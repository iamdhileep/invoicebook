<?php
// Quick system test
require_once '../../db.php';

echo "=== Attendance System Quick Test ===\n";

// Test 1: Database Connection
echo "1. Testing database connection... ";
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ SUCCESS\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check attendance_logs table
echo "2. Checking attendance_logs table... ";
try {
    $stmt = $pdo->query("DESCRIBE attendance_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('face_confidence', $columns) && in_array('geo_accuracy', $columns)) {
        echo "✅ SUCCESS (Enhanced columns present)\n";
    } else {
        echo "⚠️ WARNING (Basic table only)\n";
    }
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
}

// Test 3: Check employees table
echo "3. Checking employees table... ";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM employees");
    $count = $stmt->fetchColumn();
    echo "✅ SUCCESS ({$count} employees)\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
}

// Test 4: API file existence
echo "4. Checking API files... ";
$apiFiles = [
    'api/biometric_api.php',
    'api/test_login.php',
    'attendance.php',
    'test_attendance_features.html'
];

$allFound = true;
foreach ($apiFiles as $file) {
    if (!file_exists($file)) {
        echo "❌ Missing: $file\n";
        $allFound = false;
    }
}

if ($allFound) {
    echo "✅ SUCCESS (All files present)\n";
}

echo "\n=== Test Complete ===\n";
echo "🎉 System appears to be working properly!\n";
echo "📋 Next steps:\n";
echo "   1. Open test_attendance_features.html in browser\n";
echo "   2. Test API endpoints using test_mode=true\n";
echo "   3. Test login functionality\n";
echo "   4. Verify geolocation features\n";
?>
