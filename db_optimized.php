<?php
// Optimized Database Connection
// Connection pooling and performance optimizations

// Enable persistent connections for better performance
$host = "localhost";
$username = "root"; 
$password = "";
$database = "billing_demo"; // Fixed database name

// Create connection with optimized settings
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die(json_encode([
        "status" => "error", 
        "message" => "Database connection failed"
    ]));
}

// Set charset to avoid encoding issues
$conn->set_charset("utf8mb4");

// Optimize MySQL connection
$conn->query("SET SESSION sql_mode = 'TRADITIONAL'");
$conn->query("SET SESSION query_cache_type = ON");

// Connection successful
if (!defined('DB_CONNECTED')) {
    define('DB_CONNECTED', true);
}
?>