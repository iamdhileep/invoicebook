<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(120);

echo "=== API Performance Diagnostic Tool ===\n";
echo "Checking for slow loading issues...\n\n";

// Check if database connection is slow
echo "1. TESTING DATABASE CONNECTION SPEED...\n";
$start = microtime(true);
try {
    include 'db.php';
    $end = microtime(true);
    $dbTime = ($end - $start) * 1000;
    echo "   Database connection time: " . number_format($dbTime, 2) . " ms\n";
    
    if ($dbTime > 1000) {
        echo "   ❌ WARNING: Database connection is slow (>" . number_format($dbTime, 0) . "ms)\n";
        echo "   This could be causing API delays.\n";
    } else {
        echo "   ✅ Database connection is fast\n";
    }
    
    // Test query performance
    echo "\n2. TESTING DATABASE QUERY PERFORMANCE...\n";
    $queries = [
        "SELECT COUNT(*) FROM customers" => "Customer count",
        "SELECT COUNT(*) FROM invoices" => "Invoice count", 
        "SELECT COUNT(*) FROM expenses" => "Expense count",
        "SELECT c.name, COUNT(i.id) as cnt FROM customers c LEFT JOIN invoices i ON c.id = i.customer_id GROUP BY c.id LIMIT 5" => "Complex join query"
    ];
    
    $totalQueryTime = 0;
    foreach ($queries as $sql => $desc) {
        $queryStart = microtime(true);
        $result = $conn->query($sql);
        $queryEnd = microtime(true);
        $queryTime = ($queryEnd - $queryStart) * 1000;
        $totalQueryTime += $queryTime;
        
        echo "   $desc: " . number_format($queryTime, 2) . " ms\n";
        
        if ($queryTime > 100) {
            echo "      ❌ WARNING: This query is slow!\n";
        }
    }
    
    echo "   Total query time: " . number_format($totalQueryTime, 2) . " ms\n";
    
} catch (Exception $e) {
    echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
}

// Check file loading times
echo "\n3. TESTING FILE LOADING PERFORMANCE...\n";
$files_to_check = [
    'layouts/header.php',
    'layouts/sidebar.php', 
    'layouts/footer.php',
    'assets/js/business_reports.js',
    'assets/css/global-styles.css'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $fileSize = filesize($file);
        $start = microtime(true);
        $content = file_get_contents($file);
        $end = microtime(true);
        $loadTime = ($end - $start) * 1000;
        
        echo "   $file: " . number_format($loadTime, 2) . " ms (" . number_format($fileSize/1024, 1) . " KB)\n";
        
        if ($loadTime > 50) {
            echo "      ❌ WARNING: File loading is slow!\n";
        }
    } else {
        echo "   $file: ❌ FILE MISSING\n";
    }
}

// Check for potential infinite loops or heavy computations
echo "\n4. TESTING API ENDPOINTS (Simulated)...\n";

// Simulate the reports API logic without including the file
function simulateReportsAPI($action, $from_date, $to_date) {
    global $conn;
    
    $start = microtime(true);
    
    switch ($action) {
        case 'dashboard_stats':
            // Simulate the queries from dashboard_stats function
            $queries = [
                "SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM invoices WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'",
                "SELECT COALESCE(SUM(amount), 0) as total_expenses FROM expenses WHERE DATE(expense_date) BETWEEN '$from_date' AND '$to_date'",
                "SELECT COUNT(*) as invoice_count FROM invoices WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'"
            ];
            break;
            
        case 'financial_analysis':
            $queries = [
                "SELECT DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as invoice_count, SUM(total_amount) as revenue FROM invoices WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date' GROUP BY DATE_FORMAT(created_at, '%Y-%m')"
            ];
            break;
            
        case 'customer_analytics':
            $queries = [
                "SELECT c.name, COUNT(i.id) as total_orders, SUM(i.total_amount) as total_revenue FROM customers c LEFT JOIN invoices i ON c.id = i.customer_id WHERE i.id IS NOT NULL AND DATE(i.created_at) BETWEEN '$from_date' AND '$to_date' GROUP BY c.id LIMIT 20"
            ];
            break;
            
        default:
            $queries = ["SELECT 1"]; // Simple test query
    }
    
    $queryTime = 0;
    foreach ($queries as $sql) {
        $queryStart = microtime(true);
        $result = $conn->query($sql);
        $queryEnd = microtime(true);
        $queryTime += ($queryEnd - $queryStart) * 1000;
    }
    
    $end = microtime(true);
    $totalTime = ($end - $start) * 1000;
    
    return ['total_time' => $totalTime, 'query_time' => $queryTime];
}

$apiTests = [
    'dashboard_stats' => 'Dashboard Stats',
    'financial_analysis' => 'Financial Analysis', 
    'customer_analytics' => 'Customer Analytics'
];

foreach ($apiTests as $action => $description) {
    $result = simulateReportsAPI($action, '2024-01-01', '2024-12-31');
    echo "   $description: " . number_format($result['total_time'], 2) . " ms (queries: " . number_format($result['query_time'], 2) . " ms)\n";
    
    if ($result['total_time'] > 1000) {
        echo "      ❌ WARNING: This endpoint is slow!\n";
    }
}

// Check for common PHP performance issues
echo "\n5. CHECKING PHP CONFIGURATION FOR PERFORMANCE ISSUES...\n";

$config_checks = [
    'max_execution_time' => ['recommended' => '30', 'critical' => '300'],
    'memory_limit' => ['recommended' => '256M', 'critical' => '128M'],
    'default_socket_timeout' => ['recommended' => '60', 'critical' => '600']
];

foreach ($config_checks as $setting => $limits) {
    $value = ini_get($setting);
    echo "   $setting: $value";
    
    if ($setting === 'memory_limit') {
        $valueNum = (int)str_replace(['M', 'G'], ['', '000'], $value);
        $recNum = (int)str_replace(['M', 'G'], ['', '000'], $limits['recommended']);
        if ($valueNum < $recNum) {
            echo " ❌ (recommend at least " . $limits['recommended'] . ")";
        } else {
            echo " ✅";
        }
    } else {
        if ((int)$value > (int)$limits['critical']) {
            echo " ❌ (too high, may indicate timeouts)";
        } elseif ((int)$value > (int)$limits['recommended']) {
            echo " ⚠️ (higher than recommended)";
        } else {
            echo " ✅";
        }
    }
    echo "\n";
}

// Check opcache
echo "   OPcache enabled: " . (extension_loaded('opcache') && ini_get('opcache.enable') ? '✅ Yes' : '❌ No (recommend enabling)') . "\n";

// Check for large files that might cause loading delays  
echo "\n6. CHECKING FOR LARGE FILES THAT MIGHT CAUSE DELAYS...\n";
$large_files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('.'));
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $size = $file->getSize();
        if ($size > 1024 * 1024) { // > 1MB
            $large_files[] = ['file' => $file->getPathname(), 'size' => $size];
        }
    }
}

if (empty($large_files)) {
    echo "   ✅ No unusually large files found\n";
} else {
    echo "   Found " . count($large_files) . " large files:\n";
    foreach ($large_files as $file) {
        echo "      " . $file['file'] . " (" . number_format($file['size']/1024/1024, 2) . " MB)\n";
    }
}

// Memory usage
echo "\n7. MEMORY USAGE ANALYSIS...\n";
echo "   Peak memory usage: " . number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB\n";
echo "   Current memory usage: " . number_format(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n";

// Final recommendations
echo "\n8. PERFORMANCE RECOMMENDATIONS...\n";

if (isset($dbTime) && $dbTime > 500) {
    echo "   🔧 Database connection is slow - check MySQL configuration\n";
}

if (isset($totalQueryTime) && $totalQueryTime > 500) {
    echo "   🔧 Database queries are slow - consider adding indexes\n";
}

if (!extension_loaded('opcache') || !ini_get('opcache.enable')) {
    echo "   🔧 Enable OPcache for better PHP performance\n";
}

if ((int)str_replace('M', '', ini_get('memory_limit')) < 256) {
    echo "   🔧 Consider increasing PHP memory_limit to 256M or higher\n";
}

echo "   🔧 Consider implementing caching for frequently accessed data\n";
echo "   🔧 Consider using GZIP compression for large responses\n";

echo "\n=== DIAGNOSTIC COMPLETE ===\n";
echo "Check the warnings above for potential causes of slow loading.\n";
?>
