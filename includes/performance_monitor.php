<?php
// Performance Monitor - Track page load times and database queries
class PerformanceMonitor {
    private static $start_time;
    private static $queries = [];
    private static $memory_start;
    
    public static function start() {
        self::$start_time = microtime(true);
        self::$memory_start = memory_get_usage();
        
        // Start output buffering to catch all output
        ob_start();
    }
    
    public static function logQuery($query, $execution_time) {
        self::$queries[] = [
            'query' => $query,
            'time' => $execution_time,
            'timestamp' => microtime(true)
        ];
    }
    
    public static function end() {
        $end_time = microtime(true);
        $total_time = ($end_time - self::$start_time) * 1000;
        $memory_end = memory_get_usage();
        $memory_used = ($memory_end - self::$memory_start) / 1024 / 1024;
        
        $total_query_time = array_sum(array_column(self::$queries, 'time'));
        
        // Get the buffered content
        $content = ob_get_contents();
        ob_end_clean();
        
        // Add performance info to the page
        $performance_info = self::generatePerformanceInfo($total_time, $memory_used, $total_query_time);
        
        // Insert performance info before closing body tag
        $content = str_replace('</body>', $performance_info . '</body>', $content);
        
        echo $content;
    }
    
    private static function generatePerformanceInfo($total_time, $memory_used, $total_query_time) {
        $query_count = count(self::$queries);
        
        // Determine performance status
        $status_class = 'success';
        $status_text = 'Good';
        
        if ($total_time > 1000) {
            $status_class = 'danger';
            $status_text = 'Slow';
        } elseif ($total_time > 500) {
            $status_class = 'warning'; 
            $status_text = 'Fair';
        }
        
        return '
        <!-- Performance Monitor -->
        <div id="performanceMonitor" style="position: fixed; bottom: 10px; right: 10px; z-index: 9999; display: none;">
            <div class="card shadow-sm" style="min-width: 300px;">
                <div class="card-header bg-' . $status_class . ' text-white py-2">
                    <small><i class="fas fa-tachometer-alt"></i> Performance: ' . $status_text . '</small>
                </div>
                <div class="card-body p-2">
                    <small>
                        <div class="row g-1">
                            <div class="col-6">Load Time:</div>
                            <div class="col-6 text-end"><strong>' . round($total_time, 2) . 'ms</strong></div>
                            
                            <div class="col-6">Memory:</div>
                            <div class="col-6 text-end"><strong>' . round($memory_used, 2) . 'MB</strong></div>
                            
                            <div class="col-6">DB Queries:</div>
                            <div class="col-6 text-end"><strong>' . $query_count . '</strong></div>
                            
                            <div class="col-6">Query Time:</div>
                            <div class="col-6 text-end"><strong>' . round($total_query_time, 2) . 'ms</strong></div>
                        </div>
                    </small>
                </div>
            </div>
        </div>
        
        <script>
            // Show performance monitor on Ctrl+P
            document.addEventListener("keydown", function(e) {
                if (e.ctrlKey && e.key === "p") {
                    e.preventDefault();
                    const monitor = document.getElementById("performanceMonitor");
                    monitor.style.display = monitor.style.display === "none" ? "block" : "none";
                }
            });
            
            // Auto-show if performance is poor
            if (' . $total_time . ' > 1000) {
                document.getElementById("performanceMonitor").style.display = "block";
                setTimeout(function() {
                    document.getElementById("performanceMonitor").style.display = "none";
                }, 5000);
            }
        </script>';
    }
}

// Auto-start performance monitoring if enabled
if (isset($_GET['perf']) || isset($_COOKIE['perf_monitor'])) {
    PerformanceMonitor::start();
    
    // Set cookie to remember preference
    if (isset($_GET['perf'])) {
        setcookie('perf_monitor', '1', time() + 3600, '/');
    }
}
?>
