<?php
require 'db.php';

echo "Standardizing attendance table column names...\n";

try {
    // Add punch_in_time and punch_out_time columns, copy data from time_in/time_out
    $conn->query("ALTER TABLE attendance ADD COLUMN punch_in_time TIME AFTER date");
    echo "✅ Added punch_in_time column\n";
    
    $conn->query("ALTER TABLE attendance ADD COLUMN punch_out_time TIME AFTER punch_in_time");
    echo "✅ Added punch_out_time column\n";
    
    // Copy data from existing columns
    $conn->query("UPDATE attendance SET punch_in_time = time_in WHERE time_in IS NOT NULL");
    echo "✅ Copied time_in data to punch_in_time\n";
    
    $conn->query("UPDATE attendance SET punch_out_time = time_out WHERE time_out IS NOT NULL");
    echo "✅ Copied time_out data to punch_out_time\n";
    
    // Add additional columns for enhanced functionality
    $alterQueries = [
        "ALTER TABLE attendance ADD COLUMN location VARCHAR(255) AFTER punch_out_time",
        "ALTER TABLE attendance ADD COLUMN punch_method VARCHAR(50) DEFAULT 'manual' AFTER location",
        "ALTER TABLE attendance ADD COLUMN work_duration DECIMAL(5,2) AFTER punch_method",
        "ALTER TABLE attendance ADD COLUMN remarks TEXT AFTER work_duration"
    ];
    
    foreach ($alterQueries as $query) {
        try {
            $conn->query($query);
            echo "✅ " . substr($query, 0, 50) . "...\n";
        } catch (Exception $e) {
            echo "⚠️ Column may already exist: " . substr($query, 0, 50) . "...\n";
        }
    }
    
    // Update location from existing data if available
    $conn->query("UPDATE attendance SET location = CONCAT('Lat: ', gps_latitude, ', Lng: ', gps_longitude) WHERE gps_latitude IS NOT NULL AND gps_longitude IS NOT NULL");
    echo "✅ Updated location data from GPS coordinates\n";
    
    echo "\n✅ Attendance table standardization complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
