<?php
// Simple File-based Caching System for API responses
class SimpleCache {
    private $cache_dir = "cache/";
    private $cache_time = 300; // 5 minutes default
    
    public function __construct() {
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    public function get($key) {
        $file = $this->cache_dir . md5($key) . ".cache";
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = unserialize(file_get_contents($file));
        
        // Check if expired
        if ($data["expires"] < time()) {
            unlink($file);
            return false;
        }
        
        return $data["content"];
    }
    
    public function set($key, $content, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->cache_time;
        }
        
        $file = $this->cache_dir . md5($key) . ".cache";
        $data = [
            "content" => $content,
            "expires" => time() + $ttl
        ];
        
        return file_put_contents($file, serialize($data)) !== false;
    }
    
    public function delete($key) {
        $file = $this->cache_dir . md5($key) . ".cache";
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }
    
    public function clear() {
        $files = glob($this->cache_dir . "*.cache");
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
}

// Global cache instance
$cache = new SimpleCache();
?>