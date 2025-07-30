<?php
// Optimized Database Connection with Performance Enhancements
class OptimizedDB {
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
        
        // Enable persistent connections for better performance
        $this->connection = new mysqli($host, $user, $password, $dbname);
        
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }
        
        // Set optimal MySQL settings for performance
        $this->connection->set_charset("utf8mb4");
        $this->connection->query("SET SESSION sql_mode = ''");
        $this->connection->query("SET SESSION query_cache_type = ON");
    }
    
    public function getConnection() {
        // Check if connection is still alive
        if (!$this->connection->ping()) {
            $this->connect();
        }
        return $this->connection;
    }
    
    public function query($sql, $params = [], $cache_key = null) {
        $conn = $this->getConnection();
        
        // Check cache first if enabled and cache key provided
        if ($this->cache_enabled && $cache_key && isset($this->query_cache[$cache_key])) {
            if (time() - $this->query_cache[$cache_key]['timestamp'] < $this->cache_ttl) {
                return $this->query_cache[$cache_key]['data'];
            }
        }
        
        // Prepare and execute query
        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->execute($params);
                $result = $stmt->get_result();
                $stmt->close();
            } else {
                throw new Exception("Query preparation failed: " . $conn->error);
            }
        } else {
            $result = $conn->query($sql);
        }
        
        if (!$result) {
            throw new Exception("Query execution failed: " . $conn->error);
        }
        
        // Cache the result if cache key provided
        if ($this->cache_enabled && $cache_key && $result instanceof mysqli_result) {
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $this->query_cache[$cache_key] = [
                'data' => $data,
                'timestamp' => time()
            ];
            return $data;
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
    
    public function disableCache() {
        $this->cache_enabled = false;
    }
    
    public function enableCache() {
        $this->cache_enabled = true;
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
$optimizedDB = OptimizedDB::getInstance();
?>
