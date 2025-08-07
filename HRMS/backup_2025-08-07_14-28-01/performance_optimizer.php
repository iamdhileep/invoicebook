<?php
/**
 * Performance Optimization Implementation
 * Implements CSS/JS minification, database indexing, and caching
 */

$page_title = "Performance Optimization";
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
include '../db.php';

// Performance optimization functions
class PerformanceOptimizer {
    private $conn;
    private $cache_dir;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        $this->cache_dir = '../cache/';
        $this->ensureCacheDirectory();
    }
    
    private function ensureCacheDirectory() {
        if (!file_exists($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    /**
     * Minify CSS content
     */
    public function minifyCSS($css) {
        // Remove comments
        $css = preg_replace('/\/\*.*?\*\//', '', $css);
        // Remove whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        // Remove unnecessary spaces around specific characters
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        // Remove trailing semicolon before closing brace
        $css = preg_replace('/;+}/', '}', $css);
        return trim($css);
    }
    
    /**
     * Minify JavaScript content
     */
    public function minifyJS($js) {
        // Remove single line comments (but not URLs)
        $js = preg_replace('/(?<!:)\/\/.*$/m', '', $js);
        // Remove multi-line comments
        $js = preg_replace('/\/\*.*?\*\//s', '', $js);
        // Remove extra whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        // Remove spaces around operators
        $js = preg_replace('/\s*([=+\-*\/{}();,])\s*/', '$1', $js);
        return trim($js);
    }
    
    /**
     * Create database indexes for better performance
     */
    public function optimizeDatabase() {
        $optimizations = [];
        
        try {
            // Check if indexes exist and create if missing
            $indexes = [
                'employees' => [
                    'idx_employee_status' => 'status',
                    'idx_employee_department' => 'department_id',
                    'idx_employee_email' => 'email'
                ],
                'attendance' => [
                    'idx_attendance_employee' => 'employee_id',
                    'idx_attendance_date' => 'attendance_date',
                    'idx_attendance_status' => 'status'
                ],
                'leave_requests' => [
                    'idx_leave_employee' => 'employee_id',
                    'idx_leave_status' => 'status',
                    'idx_leave_dates' => 'start_date, end_date'
                ]
            ];
            
            foreach ($indexes as $table => $table_indexes) {
                foreach ($table_indexes as $index_name => $columns) {
                    $sql = "CREATE INDEX IF NOT EXISTS {$index_name} ON {$table} ({$columns})";
                    if ($this->conn->query($sql)) {
                        $optimizations[] = "✅ Index {$index_name} on {$table}({$columns})";
                    } else {
                        $optimizations[] = "❌ Failed to create index {$index_name}: " . $this->conn->error;
                    }
                }
            }
            
            // Optimize tables
            $tables = ['employees', 'attendance', 'leave_requests', 'departments'];
            foreach ($tables as $table) {
                if ($this->conn->query("OPTIMIZE TABLE {$table}")) {
                    $optimizations[] = "✅ Optimized table {$table}";
                }
            }
            
        } catch (Exception $e) {
            $optimizations[] = "❌ Database optimization error: " . $e->getMessage();
        }
        
        return $optimizations;
    }
    
    /**
     * Generate cache headers for static content
     */
    public function setCacheHeaders($file_type = 'static', $max_age = 3600) {
        $cache_rules = [
            'css' => 86400 * 30, // 30 days
            'js' => 86400 * 30,  // 30 days
            'images' => 86400 * 7, // 7 days
            'static' => 3600,    // 1 hour
            'dynamic' => 300     // 5 minutes
        ];
        
        $max_age = $cache_rules[$file_type] ?? $max_age;
        
        header("Cache-Control: public, max-age={$max_age}");
        header("Expires: " . gmdate('D, d M Y H:i:s', time() + $max_age) . ' GMT');
        header("Last-Modified: " . gmdate('D, d M Y H:i:s', filemtime(__FILE__)) . ' GMT');
    }
    
    /**
     * Generate optimized CSS bundle
     */
    public function generateOptimizedCSS() {
        $css_files = [
            '../layouts/header.php' // Extract CSS from header
        ];
        
        $combined_css = "/* Optimized CSS Bundle - Generated " . date('Y-m-d H:i:s') . " */\n";
        
        // Extract and combine CSS from header.php
        $header_content = file_get_contents('../layouts/header.php');
        preg_match_all('/<style[^>]*>(.*?)<\/style>/s', $header_content, $matches);
        
        foreach ($matches[1] as $css_block) {
            $combined_css .= $this->minifyCSS($css_block) . "\n";
        }
        
        // Add performance-specific CSS
        $combined_css .= $this->minifyCSS("
            .perf-optimized { will-change: transform; }
            .gpu-accelerated { transform: translateZ(0); }
            .no-select { user-select: none; }
            .preload { opacity: 0; transition: opacity 0.3s ease; }
            .loaded { opacity: 1; }
        ");
        
        return $combined_css;
    }
    
    /**
     * Get system performance metrics
     */
    public function getPerformanceMetrics() {
        $metrics = [];
        
        // Database performance
        $start_time = microtime(true);
        $result = $this->conn->query("SELECT COUNT(*) FROM hr_employees");
        $db_query_time = (microtime(true) - $start_time) * 1000;
        
        $metrics['database'] = [
            'query_time' => round($db_query_time, 2) . 'ms',
            'connection_status' => $this->conn->ping() ? 'Connected' : 'Disconnected'
        ];
        
        // Memory usage
        $metrics['memory'] = [
            'usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
            'peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
            'limit' => ini_get('memory_limit')
        ];
        
        // File system
        $metrics['filesystem'] = [
            'cache_dir_writable' => is_writable($this->cache_dir),
            'cache_dir_size' => $this->getCacheSize(),
            'temp_dir' => sys_get_temp_dir()
        ];
        
        // Server info
        $metrics['server'] = [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'max_execution_time' => ini_get('max_execution_time') . 's'
        ];
        
        return $metrics;
    }
    
    private function getCacheSize() {
        if (!is_dir($this->cache_dir)) return '0B';
        
        $size = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cache_dir)
        );
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $this->formatBytes($size);
    }
    
    private function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . $units[$i];
    }
}

// Initialize optimizer
$optimizer = new PerformanceOptimizer($conn);

// Handle optimization requests
$optimization_results = [];
if ($_POST['action'] ?? false) {
    switch ($_POST['action']) {
        case 'optimize_database':
            $optimization_results = $optimizer->optimizeDatabase();
            break;
        case 'clear_cache':
            // Clear cache implementation
            $cache_files = glob($optimizer->cache_dir . '*');
            foreach ($cache_files as $file) {
                if (is_file($file)) unlink($file);
            }
            $optimization_results[] = "✅ Cache cleared successfully";
            break;
        case 'generate_css':
            $optimized_css = $optimizer->generateOptimizedCSS();
            file_put_contents($optimizer->cache_dir . 'optimized.css', $optimized_css);
            $optimization_results[] = "✅ Optimized CSS generated";
            break;
    }
}

$metrics = $optimizer->getPerformanceMetrics();
?>

<!-- Page Content Starts Here -->
    <div class="container-fluid p-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm bg-gradient-performance text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="performance-icon me-3">
                                <i class="fas fa-tachometer-alt fa-2x"></i>
                            </div>
                            <div>
                                <h3 class="mb-1">Performance Optimization Center</h3>
                                <p class="mb-0 opacity-75">Monitor and optimize system performance</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="row mb-4">
            <!-- Database Performance -->
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm hover-lift">
                    <div class="card-header bg-gradient-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-database me-2"></i>Database</h6>
                    </div>
                    <div class="card-body">
                        <div class="metric-item">
                            <span class="metric-label">Query Time:</span>
                            <span class="metric-value text-primary"><?php echo $metrics['database']['query_time']; ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Status:</span>
                            <span class="metric-value text-success"><?php echo $metrics['database']['connection_status']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Memory Usage -->
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm hover-lift">
                    <div class="card-header bg-gradient-info text-white">
                        <h6 class="mb-0"><i class="fas fa-memory me-2"></i>Memory</h6>
                    </div>
                    <div class="card-body">
                        <div class="metric-item">
                            <span class="metric-label">Current:</span>
                            <span class="metric-value text-info"><?php echo $metrics['memory']['usage']; ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Peak:</span>
                            <span class="metric-value text-warning"><?php echo $metrics['memory']['peak']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- File System -->
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm hover-lift">
                    <div class="card-header bg-gradient-success text-white">
                        <h6 class="mb-0"><i class="fas fa-folder me-2"></i>Cache</h6>
                    </div>
                    <div class="card-body">
                        <div class="metric-item">
                            <span class="metric-label">Writable:</span>
                            <span class="metric-value <?php echo $metrics['filesystem']['cache_dir_writable'] ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $metrics['filesystem']['cache_dir_writable'] ? 'Yes' : 'No'; ?>
                            </span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Size:</span>
                            <span class="metric-value text-success"><?php echo $metrics['filesystem']['cache_dir_size']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Server Info -->
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm hover-lift">
                    <div class="card-header bg-gradient-dark text-white">
                        <h6 class="mb-0"><i class="fas fa-server me-2"></i>Server</h6>
                    </div>
                    <div class="card-body">
                        <div class="metric-item">
                            <span class="metric-label">PHP:</span>
                            <span class="metric-value text-dark"><?php echo $metrics['server']['php_version']; ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Max Time:</span>
                            <span class="metric-value text-dark"><?php echo $metrics['server']['max_execution_time']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Optimization Tools -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-warning text-white">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Optimization Tools</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="optimization-form">
                            <div class="d-grid gap-2">
                                <button type="submit" name="action" value="optimize_database" class="btn btn-primary">
                                    <i class="fas fa-database me-2"></i>Optimize Database
                                </button>
                                <button type="submit" name="action" value="generate_css" class="btn btn-success">
                                    <i class="fas fa-file-code me-2"></i>Generate Optimized CSS
                                </button>
                                <button type="submit" name="action" value="clear_cache" class="btn btn-warning">
                                    <i class="fas fa-trash me-2"></i>Clear Cache
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-info text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Optimization Results</h5>
                    </div>
                    <div class="card-body">
                        <div id="optimization-results" class="results-container">
                            <?php if (!empty($optimization_results)): ?>
                                <?php foreach ($optimization_results as $result): ?>
                                    <div class="result-item">
                                        <?php echo $result; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center">Click an optimization tool to see results</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Recommendations -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Performance Recommendations</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">Database Optimizations</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Create indexes on frequently queried columns</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Optimize table structures regularly</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Use prepared statements for security</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Implement query result caching</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-info">Frontend Optimizations</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Minify CSS and JavaScript files</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Enable GZIP compression</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Optimize images with WebP format</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Implement service worker caching</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Performance Optimization Styles -->
<style>
.bg-gradient-performance {
    background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
}

.performance-icon {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hover-lift {
    transition: all 0.3s ease;
}

.hover-lift:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.metric-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    padding: 4px 0;
    border-bottom: 1px solid #f0f0f0;
}

.metric-label {
    font-size: 0.9rem;
    color: #6c757d;
}

.metric-value {
    font-weight: 600;
    font-size: 0.9rem;
}

.optimization-form button {
    transition: all 0.3s ease;
}

.optimization-form button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.results-container {
    max-height: 300px;
    overflow-y: auto;
}

.result-item {
    padding: 8px 12px;
    margin-bottom: 4px;
    background: #f8f9fa;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
}

.bg-gradient-primary { background: linear-gradient(135deg, #007bff, #0056b3); }
.bg-gradient-success { background: linear-gradient(135deg, #28a745, #1e7e34); }
.bg-gradient-info { background: linear-gradient(135deg, #17a2b8, #117a8b); }
.bg-gradient-warning { background: linear-gradient(135deg, #ffc107, #e0a800); }
.bg-gradient-dark { background: linear-gradient(135deg, #343a40, #23272b); }
.bg-gradient-secondary { background: linear-gradient(135deg, #6c757d, #545b62); }

/* Real-time update animation */
@keyframes pulse-update {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.metric-value.updating {
    animation: pulse-update 0.5s ease;
}
</style>

<!-- Performance Monitoring JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh metrics every 30 seconds
    setInterval(refreshMetrics, 30000);
    
    // Add loading states to optimization buttons
    const optimizationForm = document.querySelector('.optimization-form');
    if (optimizationForm) {
        optimizationForm.addEventListener('submit', function(e) {
            const button = e.submitter;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
        });
    }
    
    // Performance monitoring
    monitorPagePerformance();
});

function refreshMetrics() {
    // Mark metrics as updating
    document.querySelectorAll('.metric-value').forEach(el => {
        el.classList.add('updating');
        setTimeout(() => el.classList.remove('updating'), 500);
    });
    
    // In a real implementation, this would make an AJAX call
    console.log('Refreshing performance metrics...');
}

function monitorPagePerformance() {
    if ('performance' in window) {
        const perfData = performance.getEntriesByType('navigation')[0];
        const loadTime = perfData.loadEventEnd - perfData.loadEventStart;
        
        console.log(`Performance Optimizer loaded in ${loadTime}ms`);
        
        // Track memory usage if available
        if ('memory' in performance) {
            console.log('Memory usage:', {
                used: Math.round(performance.memory.usedJSHeapSize / 1048576) + 'MB',
                total: Math.round(performance.memory.totalJSHeapSize / 1048576) + 'MB'
            });
        }
    }
}

// Optimization progress tracking
function trackOptimization(action) {
    const startTime = performance.now();
    
    return function() {
        const endTime = performance.now();
        const duration = endTime - startTime;
        console.log(`${action} completed in ${duration.toFixed(2)}ms`);
    };
}
</script>

<?php 
<?php require_once 'hrms_footer_simple.php'; ?>