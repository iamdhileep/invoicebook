<?php
echo "=== OPCACHE ENABLEMENT GUIDE ===\n";
echo "Current OPcache Status: " . (extension_loaded('opcache') && ini_get('opcache.enable') ? "ENABLED ✅" : "DISABLED ❌") . "\n\n";

if (!extension_loaded('opcache') || !ini_get('opcache.enable')) {
    echo "STEPS TO ENABLE OPCACHE FOR XAMPP:\n";
    echo "\n1. LOCATE PHP.INI FILE:\n";
    echo "   Your php.ini file is likely located at:\n";
    echo "   " . php_ini_loaded_file() . "\n\n";
    
    echo "2. EDIT PHP.INI:\n";
    echo "   Find the section [opcache] or add it at the end\n";
    echo "   Add these lines:\n\n";
    echo "   ; Enable OPcache extension\n";
    echo "   zend_extension=opcache\n";
    echo "   opcache.enable=1\n";
    echo "   opcache.enable_cli=1\n";
    echo "   opcache.memory_consumption=128\n";
    echo "   opcache.interned_strings_buffer=8\n";
    echo "   opcache.max_accelerated_files=4000\n";
    echo "   opcache.revalidate_freq=2\n";
    echo "   opcache.fast_shutdown=1\n";
    echo "   opcache.validate_timestamps=1\n\n";
    
    echo "3. RESTART XAMPP:\n";
    echo "   - Stop Apache in XAMPP Control Panel\n";
    echo "   - Start Apache again\n\n";
    
    echo "4. VERIFY OPCACHE IS ENABLED:\n";
    echo "   Run this script again or check phpinfo()\n\n";
    
    // Try to find XAMPP php.ini automatically
    $possible_paths = [
        'C:\\xampp\\php\\php.ini',
        'C:\\XAMPP\\php\\php.ini', 
        dirname(php_ini_loaded_file()),
        ini_get('cfg_file_path') . DIRECTORY_SEPARATOR . 'php.ini'
    ];
    
    echo "POSSIBLE PHP.INI LOCATIONS TO CHECK:\n";
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            echo "   ✅ FOUND: $path\n";
        } else {
            echo "   ❌ NOT FOUND: $path\n";
        }
    }
    
} else {
    echo "🎉 OPCACHE IS ALREADY ENABLED!\n";
    
    // Show OPcache statistics
    if (function_exists('opcache_get_status')) {
        $status = opcache_get_status();
        echo "\nOPCACHE STATISTICS:\n";
        echo "   Memory used: " . number_format($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
        echo "   Free memory: " . number_format($status['memory_usage']['free_memory'] / 1024 / 1024, 2) . " MB\n";
        echo "   Cached scripts: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
        echo "   Cache hits: " . $status['opcache_statistics']['hits'] . "\n";
        echo "   Cache misses: " . $status['opcache_statistics']['misses'] . "\n";
        echo "   Hit rate: " . number_format($status['opcache_statistics']['opcache_hit_rate'], 2) . "%\n";
    }
}

echo "\n=== PERFORMANCE IMPACT ESTIMATE ===\n";
echo "Enabling OPcache typically provides:\n";
echo "• 2-5x faster PHP execution\n";
echo "• 50-80% reduction in CPU usage\n";
echo "• Faster page load times\n";
echo "• Better server response under load\n\n";

echo "After enabling OPcache, your API response times should improve from ~40ms to ~10-15ms\n";

// Test current performance
echo "\n=== CURRENT PERFORMANCE BASELINE ===\n";
$start = microtime(true);

// Simulate typical API operations
include_once 'db_optimized.php';
$result = $conn->query("SELECT COUNT(*) FROM customers");
$result = $conn->query("SELECT COUNT(*) FROM invoices");  
$result = $conn->query("SELECT COUNT(*) FROM expenses");

$end = microtime(true);
$time_ms = ($end - $start) * 1000;

echo "Basic operations time: " . number_format($time_ms, 2) . " ms\n";

if ($time_ms > 50) {
    echo "⚠️ Performance could be better - enable OPcache!\n";
} else {
    echo "✅ Good performance detected\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
?>
