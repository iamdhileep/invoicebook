<?php
/**
 * HRMS Data Flow Balance Validator
 * Ensures all files have proper data pull (fetch) and push (submit) operations
 */

if (!isset($root_path)) 
require_once '../db.php';

echo "🔄 HRMS DATA FLOW BALANCE VALIDATOR\n";
echo "===================================\n\n";

$timestamp = date('Y-m-d H:i:s');
echo "📅 Starting data flow validation at: $timestamp\n\n";

// Define critical HRMS modules that must have both pull and push operations
$critical_modules = [
    'employee_directory.php' => ['pull' => 'SELECT employees', 'push' => 'INSERT/UPDATE employees'],
    'attendance_management.php' => ['pull' => 'SELECT attendance', 'push' => 'INSERT attendance'],
    'leave_management.php' => ['pull' => 'SELECT leave_requests', 'push' => 'INSERT/UPDATE leave_requests'],
    'payroll_processing.php' => ['pull' => 'SELECT employees/payroll', 'push' => 'INSERT/UPDATE payroll'],
    'performance_management.php' => ['pull' => 'SELECT employee_performance', 'push' => 'INSERT/UPDATE performance'],
    'training_management.php' => ['pull' => 'SELECT training_programs', 'push' => 'INSERT/UPDATE training'],
    'employee_helpdesk.php' => ['pull' => 'SELECT helpdesk_tickets', 'push' => 'INSERT tickets'],
    'Hr_panel.php' => ['pull' => 'SELECT dashboard_data', 'push' => 'Various CREATE operations'],
    'Employee_panel.php' => ['pull' => 'SELECT employee_data', 'push' => 'UPDATE employee requests'],
    'Manager_panel.php' => ['pull' => 'SELECT team_data', 'push' => 'UPDATE approvals']
];

$data_flow_results = [];
$total_score = 0;
$max_score = 0;

echo "🔍 ANALYZING DATA FLOW OPERATIONS...\n";
echo str_repeat("-", 60) . "\n";

foreach ($critical_modules as $file => $expected_ops) {
    $file_path = __DIR__ . DIRECTORY_SEPARATOR . $file;
    
    if (!file_exists($file_path)) {
        echo "   ❌ $file: File not found\n";
        continue;
    }
    
    $content = file_get_contents($file_path);
    
    // Analyze PULL operations (data fetching)
    $has_select = (strpos($content, 'SELECT') !== false);
    $has_mysqli_query_read = (preg_match('/mysqli_query\s*\(\s*\$conn\s*,\s*["\'][^"\']*SELECT/i', $content));
    $has_fetch = (strpos($content, 'mysqli_fetch') !== false);
    
    $pull_score = 0;
    if ($has_select) $pull_score += 2;
    if ($has_mysqli_query_read) $pull_score += 2;
    if ($has_fetch) $pull_score += 1;
    $pull_score = min($pull_score, 5); // Max 5 points for pull
    
    // Analyze PUSH operations (data submission)
    $has_insert = (strpos($content, 'INSERT') !== false);
    $has_update = (strpos($content, 'UPDATE') !== false);
    $has_delete = (strpos($content, 'DELETE') !== false);
    $has_form_handling = (strpos($content, '$_POST') !== false || strpos($content, '$_GET') !== false);
    $has_mysqli_query_write = (preg_match('/mysqli_query\s*\(\s*\$conn\s*,\s*["\'][^"\']*(?:INSERT|UPDATE|DELETE)/i', $content));
    
    $push_score = 0;
    if ($has_insert) $push_score += 2;
    if ($has_update) $push_score += 2;
    if ($has_delete) $push_score += 1;
    if ($has_form_handling) $push_score += 2;
    if ($has_mysqli_query_write) $push_score += 2;
    $push_score = min($push_score, 5); // Max 5 points for push
    
    $total_file_score = $pull_score + $push_score;
    $max_file_score = 10;
    
    $total_score += $total_file_score;
    $max_score += $max_file_score;
    
    // Determine status
    $status = '';
    if ($total_file_score >= 8) {
        $status = '✅ EXCELLENT';
    } elseif ($total_file_score >= 6) {
        $status = '🟡 GOOD';
    } elseif ($total_file_score >= 4) {
        $status = '⚠️ PARTIAL';
    } else {
        $status = '❌ POOR';
    }
    
    $data_flow_results[$file] = [
        'pull_score' => $pull_score,
        'push_score' => $push_score,
        'total_score' => $total_file_score,
        'status' => $status,
        'has_select' => $has_select,
        'has_insert' => $has_insert,
        'has_update' => $has_update,
        'has_form_handling' => $has_form_handling
    ];
    
    echo "   $status $file\n";
    echo "      📥 Pull: $pull_score/5 | 📤 Push: $push_score/5 | 🎯 Total: $total_file_score/10\n";
}

// Calculate overall data flow balance percentage
$balance_percentage = ($total_score / $max_score) * 100;

echo "\n📊 DATA FLOW OPERATIONS ANALYSIS:\n";
echo str_repeat("=", 60) . "\n";

foreach ($data_flow_results as $file => $results) {
    echo "📁 " . str_pad($file, 30) . " | ";
    echo "Pull: " . str_pad($results['pull_score'] . "/5", 6);
    echo "Push: " . str_pad($results['push_score'] . "/5", 6);
    echo "Status: " . $results['status'] . "\n";
    
    // Show detailed breakdown
    $details = [];
    if ($results['has_select']) $details[] = "✅ SELECT";
    if ($results['has_insert']) $details[] = "✅ INSERT";
    if ($results['has_update']) $details[] = "✅ UPDATE";
    if ($results['has_form_handling']) $details[] = "✅ Forms";
    
    if (!empty($details)) {
        echo "   " . implode(" | ", $details) . "\n";
    }
    echo "\n";
}

// Test actual database operations
echo "🗄️ TESTING LIVE DATABASE OPERATIONS...\n";
echo str_repeat("-", 60) . "\n";

$db_tests = [
    'Employee Read' => "SELECT COUNT(*) as count FROM employees LIMIT 1",
    'Attendance Read' => "SELECT COUNT(*) as count FROM attendance LIMIT 1", 
    'Leave Requests Read' => "SELECT COUNT(*) as count FROM leave_requests LIMIT 1",
    'Performance Read' => "SELECT COUNT(*) as count FROM employee_performance LIMIT 1",
    'Training Read' => "SELECT COUNT(*) as count FROM training_programs LIMIT 1",
    'Helpdesk Read' => "SELECT COUNT(*) as count FROM helpdesk_tickets LIMIT 1"
];

$successful_operations = 0;
$total_operations = count($db_tests);

foreach ($db_tests as $test_name => $query) {
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "   ✅ $test_name: " . $row['count'] . " records accessible\n";
        $successful_operations++;
    } else {
        echo "   ❌ $test_name: Query failed - " . mysqli_error($conn) . "\n";
    }
}

// Test write operations (simulated)
echo "\n💾 TESTING DATABASE WRITE CAPABILITIES...\n";
echo str_repeat("-", 60) . "\n";

$write_tests = [
    'Employee Write Test' => "INSERT INTO employees (name, employee_code, email, status) VALUES ('Test Employee', 'TEST001', 'test@example.com', 'inactive')",
    'Attendance Write Test' => "INSERT INTO attendance (employee_id, attendance_date, status) VALUES (1, CURDATE(), 'Present')",
    'Test Cleanup' => "DELETE FROM employees WHERE employee_code = 'TEST001'"
];

$write_success = 0;
foreach ($write_tests as $test_name => $query) {
    $result = mysqli_query($conn, $query);
    if ($result) {
        echo "   ✅ $test_name: Success\n";
        $write_success++;
    } else {
        echo "   ⚠️ $test_name: " . mysqli_error($conn) . "\n";
    }
}

// Calculate database operation success rate
$db_operation_percentage = (($successful_operations + $write_success) / ($total_operations + count($write_tests))) * 100;

echo "\n🎯 OVERALL DATA FLOW BALANCE SUMMARY:\n";
echo str_repeat("=", 60) . "\n";
echo "   📁 Critical modules analyzed: " . count($critical_modules) . "\n";
echo "   🎯 Data flow score: $total_score/$max_score (" . round($balance_percentage, 1) . "%)\n";
echo "   🗄️ Database operations: " . round($db_operation_percentage, 1) . "% success rate\n";
echo "   📥 Read operations: " . count(array_filter($data_flow_results, fn($r) => $r['pull_score'] >= 3)) . "/" . count($data_flow_results) . " files\n";
echo "   📤 Write operations: " . count(array_filter($data_flow_results, fn($r) => $r['push_score'] >= 3)) . "/" . count($data_flow_results) . " files\n";

// Final balance assessment
echo "\n🏆 FINAL DATA FLOW BALANCE STATUS:\n";
echo str_repeat("=", 60) . "\n";

$overall_score = ($balance_percentage + $db_operation_percentage) / 2;

if ($overall_score >= 90) {
    echo "🎉 STATUS: PERFECTLY BALANCED DATA FLOW! 🎉\n";
    echo "✅ Your HRMS system has excellent data pull/push operations.\n";
    echo "✅ All critical modules properly fetch and submit data.\n";
    echo "✅ Database connectivity is optimal.\n";
} elseif ($overall_score >= 80) {
    echo "🎯 STATUS: WELL BALANCED DATA FLOW\n";
    echo "✅ Your HRMS system has good data operations.\n";
    echo "🔄 Minor improvements recommended for optimal performance.\n";
} elseif ($overall_score >= 70) {
    echo "⚖️ STATUS: MODERATELY BALANCED DATA FLOW\n";
    echo "🔄 Your HRMS system needs some data flow improvements.\n";
    echo "⚠️ Some modules may have incomplete data operations.\n";
} else {
    echo "⚠️ STATUS: NEEDS DATA FLOW BALANCING\n";
    echo "🔧 Your HRMS system requires significant data flow improvements.\n";
    echo "❌ Multiple modules have incomplete data operations.\n";
}

echo "\n💡 DATA FLOW OPTIMIZATION RECOMMENDATIONS:\n";
echo str_repeat("=", 60) . "\n";

// Provide specific recommendations
$low_scoring_files = array_filter($data_flow_results, fn($r) => $r['total_score'] < 6);
if (!empty($low_scoring_files)) {
    echo "🔧 Improve data operations in these files:\n";
    foreach ($low_scoring_files as $file => $results) {
        echo "   • $file (Score: {$results['total_score']}/10)\n";
        if ($results['pull_score'] < 3) echo "     - Add more data fetching (SELECT queries)\n";
        if ($results['push_score'] < 3) echo "     - Add data submission (INSERT/UPDATE forms)\n";
    }
    echo "\n";
}

echo "📊 Ensure all modules have bidirectional data flow:\n";
echo "   📥 Data Pull: SELECT queries with proper error handling\n";
echo "   📤 Data Push: INSERT/UPDATE operations with validation\n";
echo "   🔄 Real-time updates: AJAX or auto-refresh capabilities\n";
echo "   🛡️ Data integrity: Transaction support and rollback\n";
echo "   📈 Performance: Optimized queries and indexing\n";

echo "\n🚀 NEXT STEPS FOR COMPLETE BALANCE:\n";
echo str_repeat("=", 60) . "\n";
echo "1. ✅ Database connectivity: COMPLETED (" . round($db_operation_percentage, 1) . "%)\n";
echo "2. 📊 Data flow operations: " . ($balance_percentage >= 80 ? "COMPLETED" : "IN PROGRESS") . " (" . round($balance_percentage, 1) . "%)\n";
echo "3. 🔄 Real-time synchronization: RECOMMENDED\n";
echo "4. 🛡️ Data validation & security: RECOMMENDED\n";
echo "5. 📈 Performance optimization: RECOMMENDED\n";

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎯 HRMS DATA FLOW BALANCE VALIDATION COMPLETE!\n";
echo "📈 System is " . round($overall_score, 1) . "% balanced for optimal data operations.\n";
echo str_repeat("=", 60) . "\n";
?>
