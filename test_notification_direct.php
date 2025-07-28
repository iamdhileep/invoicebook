<?php
require_once 'db.php';

// Test direct notification creation
try {
    $query = "INSERT INTO notifications (employee_id, title, message, type, is_read, created_at) 
             VALUES (?, ?, ?, ?, 0, NOW())";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo "Prepare failed: " . $conn->error . "\n";
        exit;
    }
    
    $employee_id = 23; // Using existing employee ID from our check
    $title = "Test Notification";
    $message = "This is a test notification";
    $type = "info";
    
    $stmt->bind_param("isss", $employee_id, $title, $message, $type);
    
    if ($stmt->execute()) {
        echo "✅ Notification created successfully! ID: " . $conn->insert_id . "\n";
        
        // Verify it was created
        $verify = $conn->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 1");
        if ($verify) {
            $notification = $verify->fetch_assoc();
            echo "✅ Verification: " . json_encode($notification, JSON_PRETTY_PRINT) . "\n";
        }
    } else {
        echo "❌ Execute failed: " . $stmt->error . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
