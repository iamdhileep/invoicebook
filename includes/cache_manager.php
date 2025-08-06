<?php
/**
 * Advanced Caching System for HRMS
 * Implements multiple caching strategies for optimal performance
 */

class CacheManager {
    private $cache_dir;
    private $default_ttl;
    private $compression_enabled;
    
    public function __construct($cache_dir = '../cache/', $default_ttl = 3600) {
        $this->cache_dir = rtrim($cache_dir, '/') . '/';
        $this->default_ttl = $default_ttl;
        $this->compression_enabled = function_exists('gzcompress');
        $this->ensureCacheDirectory();
    }
    
    private function ensureCacheDirectory() {
        if (!file_exists($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
        
        // Create subdirectories for different cache types
        $subdirs = ['data', 'queries', 'templates', 'assets', 'sessions'];
        foreach ($subdirs as $subdir) {
            $path = $this->cache_dir . $subdir;
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
    
    /**
     * Generate cache key from various inputs
     */
    private function generateKey($key, $prefix = '') {
        $key = $prefix . md5(serialize($key));
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    }
    
    /**
     * Get cache file path
     */
    private function getCacheFile($key, $type = 'data') {
        return $this->cache_dir . $type . '/' . $this->generateKey($key) . '.cache';
    }
    
    /**
     * Store data in cache
     */
    public function set($key, $data, $ttl = null, $type = 'data') {
        $ttl = $ttl ?? $this->default_ttl;
        $cache_file = $this->getCacheFile($key, $type);
        
        $cache_data = [
            'data' => $data,
            'created' => time(),
            'ttl' => $ttl,
            'expires' => time() + $ttl,
            'version' => '1.0'
        ];
        
        $serialized = serialize($cache_data);
        
        // Compress if enabled and data is large enough
        if ($this->compression_enabled && strlen($serialized) > 1024) {
            $serialized = gzcompress($serialized, 6);
            $cache_data['compressed'] = true;
        }
        
        return file_put_contents($cache_file, $serialized, LOCK_EX) !== false;
    }
    
    /**
     * Retrieve data from cache
     */
    public function get($key, $type = 'data') {
        $cache_file = $this->getCacheFile($key, $type);
        
        if (!file_exists($cache_file)) {
            return null;
        }
        
        $contents = file_get_contents($cache_file);
        
        // Try to decompress if it looks compressed
        if ($this->compression_enabled && function_exists('gzuncompress')) {
            $decompressed = @gzuncompress($contents);
            if ($decompressed !== false) {
                $contents = $decompressed;
            }
        }
        
        $cache_data = @unserialize($contents);
        
        if ($cache_data === false) {
            // Cache file is corrupted, remove it
            unlink($cache_file);
            return null;
        }
        
        // Check if cache has expired
        if (time() > $cache_data['expires']) {
            unlink($cache_file);
            return null;
        }
        
        return $cache_data['data'];
    }
    
    /**
     * Delete specific cache entry
     */
    public function delete($key, $type = 'data') {
        $cache_file = $this->getCacheFile($key, $type);
        
        if (file_exists($cache_file)) {
            return unlink($cache_file);
        }
        
        return true;
    }
    
    /**
     * Clear all cache or specific type
     */
    public function clear($type = null) {
        $cleared = 0;
        
        if ($type) {
            $cache_dir = $this->cache_dir . $type . '/';
        } else {
            $cache_dir = $this->cache_dir;
        }
        
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*');
            foreach ($files as $file) {
                if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'cache') {
                    if (unlink($file)) {
                        $cleared++;
                    }
                }
            }
        }
        
        return $cleared;
    }
    
    /**
     * Database query caching with automatic invalidation
     */
    public function cacheQuery($sql, $params = [], $ttl = 300) {
        $cache_key = ['query' => $sql, 'params' => $params];
        
        // Try to get from cache first
        $cached_result = $this->get($cache_key, 'queries');
        if ($cached_result !== null) {
            return $cached_result;
        }
        
        // If not in cache, we'll return null and let the caller execute the query
        // Then they should call setCachedQuery with the results
        return null;
    }
    
    /**
     * Store query results in cache
     */
    public function setCachedQuery($sql, $params, $results, $ttl = 300) {
        $cache_key = ['query' => $sql, 'params' => $params];
        return $this->set($cache_key, $results, $ttl, 'queries');
    }
    
    /**
     * Template caching for rendered HTML
     */
    public function cacheTemplate($template_name, $data, $rendered_content, $ttl = 1800) {
        $cache_key = ['template' => $template_name, 'data_hash' => md5(serialize($data))];
        return $this->set($cache_key, $rendered_content, $ttl, 'templates');
    }
    
    /**
     * Get cached template
     */
    public function getCachedTemplate($template_name, $data) {
        $cache_key = ['template' => $template_name, 'data_hash' => md5(serialize($data))];
        return $this->get($cache_key, 'templates');
    }
    
    /**
     * Session-based caching for user-specific data
     */
    public function setUserCache($user_id, $key, $data, $ttl = 1800) {
        $cache_key = ['user' => $user_id, 'key' => $key];
        return $this->set($cache_key, $data, $ttl, 'sessions');
    }
    
    /**
     * Get user-specific cached data
     */
    public function getUserCache($user_id, $key) {
        $cache_key = ['user' => $user_id, 'key' => $key];
        return $this->get($cache_key, 'sessions');
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        $stats = [
            'total_size' => 0,
            'total_files' => 0,
            'by_type' => []
        ];
        
        $types = ['data', 'queries', 'templates', 'assets', 'sessions'];
        
        foreach ($types as $type) {
            $type_dir = $this->cache_dir . $type . '/';
            $type_stats = [
                'files' => 0,
                'size' => 0,
                'oldest' => null,
                'newest' => null
            ];
            
            if (is_dir($type_dir)) {
                $files = glob($type_dir . '*.cache');
                $type_stats['files'] = count($files);
                
                foreach ($files as $file) {
                    $size = filesize($file);
                    $type_stats['size'] += $size;
                    $stats['total_size'] += $size;
                    
                    $mtime = filemtime($file);
                    if ($type_stats['oldest'] === null || $mtime < $type_stats['oldest']) {
                        $type_stats['oldest'] = $mtime;
                    }
                    if ($type_stats['newest'] === null || $mtime > $type_stats['newest']) {
                        $type_stats['newest'] = $mtime;
                    }
                }
                
                $stats['total_files'] += $type_stats['files'];
            }
            
            $stats['by_type'][$type] = $type_stats;
        }
        
        return $stats;
    }
    
    /**
     * Cleanup expired cache entries
     */
    public function cleanup() {
        $cleaned = 0;
        $types = ['data', 'queries', 'templates', 'assets', 'sessions'];
        
        foreach ($types as $type) {
            $type_dir = $this->cache_dir . $type . '/';
            
            if (is_dir($type_dir)) {
                $files = glob($type_dir . '*.cache');
                
                foreach ($files as $file) {
                    $contents = @file_get_contents($file);
                    
                    if ($this->compression_enabled && function_exists('gzuncompress')) {
                        $decompressed = @gzuncompress($contents);
                        if ($decompressed !== false) {
                            $contents = $decompressed;
                        }
                    }
                    
                    $cache_data = @unserialize($contents);
                    
                    // Remove if corrupted or expired
                    if ($cache_data === false || time() > $cache_data['expires']) {
                        if (unlink($file)) {
                            $cleaned++;
                        }
                    }
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Cache warming for frequently accessed data
     */
    public function warmup($database_connection) {
        $warmed = 0;
        
        try {
            // Warm up common queries
            $common_queries = [
                "SELECT COUNT(*) as total FROM employees WHERE status = 'active'",
                "SELECT COUNT(*) as total FROM departments",
                "SELECT * FROM departments ORDER BY name",
                "SELECT status, COUNT(*) as count FROM employees GROUP BY status"
            ];
            
            foreach ($common_queries as $query) {
                $result = $database_connection->query($query);
                if ($result) {
                    $data = [];
                    while ($row = $result->fetch_assoc()) {
                        $data[] = $row;
                    }
                    
                    if ($this->setCachedQuery($query, [], $data, 900)) { // 15 minutes
                        $warmed++;
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Cache warmup error: " . $e->getMessage());
        }
        
        return $warmed;
    }
    
    /**
     * Format bytes for display
     */
    private function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Get formatted statistics for display
     */
    public function getFormattedStats() {
        $stats = $this->getStats();
        
        $formatted = [
            'total_size' => $this->formatBytes($stats['total_size']),
            'total_files' => number_format($stats['total_files']),
            'by_type' => []
        ];
        
        foreach ($stats['by_type'] as $type => $type_stats) {
            $formatted['by_type'][$type] = [
                'files' => number_format($type_stats['files']),
                'size' => $this->formatBytes($type_stats['size']),
                'oldest' => $type_stats['oldest'] ? date('Y-m-d H:i:s', $type_stats['oldest']) : 'N/A',
                'newest' => $type_stats['newest'] ? date('Y-m-d H:i:s', $type_stats['newest']) : 'N/A'
            ];
        }
        
        return $formatted;
    }
}

// Usage example and testing
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    header('Content-Type: application/json');
    
    $cache = new CacheManager();
    
    $action = $_GET['action'] ?? 'stats';
    
    switch ($action) {
        case 'test':
            // Test cache functionality
            $test_data = ['message' => 'Hello Cache!', 'timestamp' => time()];
            $cache->set('test_key', $test_data, 60);
            $retrieved = $cache->get('test_key');
            
            echo json_encode([
                'stored' => $test_data,
                'retrieved' => $retrieved,
                'match' => $test_data === $retrieved
            ]);
            break;
            
        case 'cleanup':
            $cleaned = $cache->cleanup();
            echo json_encode(['cleaned_files' => $cleaned]);
            break;
            
        case 'clear':
            $type = $_GET['type'] ?? null;
            $cleared = $cache->clear($type);
            echo json_encode(['cleared_files' => $cleared, 'type' => $type ?: 'all']);
            break;
            
        case 'stats':
        default:
            echo json_encode($cache->getFormattedStats());
            break;
    }
}
?>
