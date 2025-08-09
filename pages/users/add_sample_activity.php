<?php
session_start();
include '../../db.php';

echo "<h3>Adding Sample Activity Data</h3>";

if ($conn) {
    // First ensure we have users
    $user_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
    
    if ($user_count == 0) {
        // Create sample users
        $users = [
            ['john_doe', 'john@example.com', 'John Doe'],
            ['jane_smith', 'jane@example.com', 'Jane Smith'],
            ['admin_user', 'admin@example.com', 'Admin User']
        ];
        
        foreach ($users as $user) {
            $stmt = $conn->prepare("INSERT INTO users (username, email, full_name, password) VALUES (?, ?, ?, ?)");
            $password = password_hash('password123', PASSWORD_DEFAULT);
            $stmt->bind_param("ssss", $user[0], $user[1], $user[2], $password);
            $stmt->execute();
        }
        echo "<p>✅ Created 3 sample users</p>";
    }
    
    // Get user IDs
    $users = [];
    $result = mysqli_query($conn, "SELECT id, username FROM users LIMIT 5");
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    
    // Sample activities
    $activities = [
        ['Login', 'User logged into the system'],
        ['View Dashboard', 'Accessed the main dashboard'],
        ['Create Invoice', 'Created a new invoice'],
        ['Update Profile', 'Modified user profile information'],
        ['Export Data', 'Exported activity logs to CSV'],
        ['Logout', 'User logged out of the system'],
        ['Password Change', 'Changed account password'],
        ['View Reports', 'Accessed reporting module'],
        ['Delete Item', 'Removed an item from inventory'],
        ['Send Email', 'Sent notification email to client']
    ];
    
    $ips = ['192.168.1.100', '192.168.1.101', '10.0.0.50', '172.16.0.10'];
    $user_agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36'
    ];
    
    // Insert sample activity logs
    $inserted = 0;
    for ($i = 0; $i < 50; $i++) {
        $user = $users[array_rand($users)];
        $activity = $activities[array_rand($activities)];
        $ip = $ips[array_rand($ips)];
        $ua = $user_agents[array_rand($user_agents)];
        
        // Random timestamp within last 7 days
        $timestamp = date('Y-m-d H:i:s', time() - rand(0, 7 * 24 * 60 * 60));
        
        $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $user['id'], $activity[0], $activity[1], $ip, $ua, $timestamp);
        
        if ($stmt->execute()) {
            $inserted++;
        }
    }
    
    echo "<p>✅ Inserted $inserted activity log entries</p>";
    
    // Create sample user sessions
    foreach (array_slice($users, 0, 3) as $user) {
        $session_id = 'sess_' . uniqid();
        $ip = $ips[array_rand($ips)];
        $ua = $user_agents[array_rand($user_agents)];
        
        $stmt = $conn->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("isss", $user['id'], $session_id, $ip, $ua);
        $stmt->execute();
    }
    
    echo "<p>✅ Created 3 active user sessions</p>";
    
    echo "<hr>";
    echo "<p><strong>Sample data creation complete!</strong></p>";
    echo "<p><a href='activity_monitor.php' class='btn btn-primary'>Go to Activity Monitor</a></p>";
    
} else {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
}
?>
