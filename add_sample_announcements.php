<?php
require_once('db.php');

echo "<h2>Adding Sample Announcements Data</h2>";

// First, ensure we have an author in hr_employees
$admin_id = 1; // Assuming admin user ID is 1
$check_author = $conn->query("SELECT id FROM hr_employees WHERE employee_id = '$admin_id' OR id = $admin_id");

if ($check_author->num_rows === 0) {
    // Create admin user in hr_employees
    $insert_admin = "INSERT INTO hr_employees (employee_id, first_name, last_name, email, position, department, status, date_of_joining) 
                     VALUES ('ADMIN001', 'System', 'Administrator', 'admin@company.com', 'Administrator', 'Management', 'active', NOW())";
    if ($conn->query($insert_admin)) {
        $admin_employee_id = $conn->insert_id;
        echo "✓ Created admin employee record (ID: $admin_employee_id)<br>";
    } else {
        echo "✗ Error creating admin employee: " . $conn->error . "<br>";
        $admin_employee_id = 1; // Fallback
    }
} else {
    $admin_employee_id = $check_author->fetch_assoc()['id'];
    echo "✓ Found existing admin employee record (ID: $admin_employee_id)<br>";
}

// Sample announcements data
$announcements = [
    [
        'title' => 'Welcome to Our New HRMS System',
        'content' => 'We are excited to announce the launch of our new Human Resource Management System. This comprehensive platform will help streamline our HR processes, improve employee experience, and enhance overall productivity. Please take some time to explore the new features and functionalities.',
        'type' => 'general',
        'priority' => 'high',
        'target_audience' => 'all',
        'department_id' => null,
        'publish_date' => date('Y-m-d'),
        'expiry_date' => date('Y-m-d', strtotime('+30 days'))
    ],
    [
        'title' => 'Updated Leave Policy - Effective Immediately',
        'content' => 'Please note that our company leave policy has been updated with new guidelines for sick leave, vacation days, and emergency leave. All employees are required to review the updated policy document available in the employee portal. For any questions, please contact the HR department.',
        'type' => 'policy',
        'priority' => 'urgent',
        'target_audience' => 'all',
        'department_id' => null,
        'publish_date' => date('Y-m-d'),
        'expiry_date' => null
    ],
    [
        'title' => 'IT Department Security Training',
        'content' => 'Mandatory cybersecurity training session for all IT department members scheduled for next Friday at 2:00 PM in Conference Room A. Topics will include password security, phishing awareness, and data protection protocols. Attendance is required.',
        'type' => 'event',
        'priority' => 'medium',
        'target_audience' => 'department',
        'department_id' => 1, // Assuming IT department ID is 1
        'publish_date' => date('Y-m-d'),
        'expiry_date' => date('Y-m-d', strtotime('+7 days'))
    ],
    [
        'title' => 'Employee of the Month - Congratulations!',
        'content' => 'We are pleased to announce that John Doe from the Sales Department has been selected as Employee of the Month for his outstanding performance and dedication. John will receive a certificate of recognition and a bonus. Congratulations John!',
        'type' => 'celebration',
        'priority' => 'low',
        'target_audience' => 'all',
        'department_id' => null,
        'publish_date' => date('Y-m-d'),
        'expiry_date' => date('Y-m-d', strtotime('+14 days'))
    ],
    [
        'title' => 'System Maintenance - Weekend Downtime',
        'content' => 'Please be advised that our HRMS system will undergo scheduled maintenance this Saturday from 10:00 PM to 2:00 AM Sunday. During this time, the system will be temporarily unavailable. We apologize for any inconvenience and appreciate your understanding.',
        'type' => 'urgent',
        'priority' => 'urgent',
        'target_audience' => 'all',
        'department_id' => null,
        'publish_date' => date('Y-m-d'),
        'expiry_date' => date('Y-m-d', strtotime('+3 days'))
    ],
    [
        'title' => 'Q3 Performance Reviews Now Open',
        'content' => 'The Q3 performance review cycle is now open in the HRMS system. All employees should complete their self-assessments by the end of this month. Managers should schedule review meetings with their team members. Please contact HR if you need assistance with the process.',
        'type' => 'general',
        'priority' => 'medium',
        'target_audience' => 'all',
        'department_id' => null,
        'publish_date' => date('Y-m-d'),
        'expiry_date' => date('Y-m-d', strtotime('+21 days'))
    ]
];

echo "<h3>Inserting sample announcements...</h3>";

foreach ($announcements as $index => $announcement) {
    $stmt = $conn->prepare("INSERT INTO hr_announcements (title, content, type, priority, target_audience, department_id, author_id, publish_date, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssiiss", 
        $announcement['title'],
        $announcement['content'],
        $announcement['type'],
        $announcement['priority'],
        $announcement['target_audience'],
        $announcement['department_id'],
        $admin_employee_id,
        $announcement['publish_date'],
        $announcement['expiry_date']
    );
    
    if ($stmt->execute()) {
        echo "✓ Added: " . htmlspecialchars($announcement['title']) . "<br>";
    } else {
        echo "✗ Error adding announcement " . ($index + 1) . ": " . $conn->error . "<br>";
    }
}

echo "<h3>✅ Sample announcements data insertion completed!</h3>";
echo "<p><strong>Summary:</strong></p>";
echo "<ul>";
echo "<li>6 sample announcements added with different types and priorities</li>";
echo "<li>Mix of general, policy, event, urgent, and celebration announcements</li>";
echo "<li>Different target audiences (all employees, specific departments)</li>";
echo "<li>Various expiry dates for demonstration</li>";
echo "</ul>";

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #2c3e50; }
h3 { color: #3498db; }
</style>
