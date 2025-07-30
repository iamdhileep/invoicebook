<?php
// Simplified Optimized Database Connection
class SimpleOptimizedDB {
    private static $instance = null;
    private $connection;
    private $query_cache = [];
    private $cache_enabled = true;
    private $cache_ttl = 300; // 5 minutes
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        $host = "localhost";
        $user = "root";
        $password = "";
        $dbname = "billing_demo";
        
        $this->connection = new mysqli($host, $user, $password, $dbname);
        
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }
        
        // Set optimal MySQL settings for performance
        $this->connection->set_charset("utf8mb4");
    }
    
    public function getConnection() {
        // Check if connection is still alive
        if (!$this->connection->ping()) {
            $this->connect();
        }
        return $this->connection;
    }
    
    public function queryCached($sql, $cache_key = null, $cache_minutes = 5) {
        // Check cache first if enabled and cache key provided
        if ($this->cache_enabled && $cache_key && isset($this->query_cache[$cache_key])) {
            $cache_age = time() - $this->query_cache[$cache_key]['timestamp'];
            if ($cache_age < ($cache_minutes * 60)) {
                return $this->query_cache[$cache_key]['data'];
            }
        }
        
        $conn = $this->getConnection();
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception("Query execution failed: " . $conn->error);
        }
        
        // Convert result to array for caching
        $data = [];
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        
        // Cache the result if cache key provided
        if ($this->cache_enabled && $cache_key) {
            $this->query_cache[$cache_key] = [
                'data' => $data,
                'timestamp' => time()
            ];
        }
        
        return $data;
    }
    
    public function query($sql) {
        $conn = $this->getConnection();
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception("Query execution failed: " . $conn->error);
        }
        
        return $result;
    }
    
    public function clearCache($cache_key = null) {
        if ($cache_key) {
            unset($this->query_cache[$cache_key]);
        } else {
            $this->query_cache = [];
        }
    }
    
    public function getCacheStats() {
        return [
            'entries' => count($this->query_cache),
            'enabled' => $this->cache_enabled,
            'ttl' => $this->cache_ttl
        ];
    }
}

// For backward compatibility, create the traditional connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "billing_demo";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set optimal settings
$conn->set_charset("utf8mb4");

// Get optimized DB instance for high-performance queries
$simpleOptimizedDB = SimpleOptimizedDB::getInstance();
?>
