<?php
include 'db.php';

echo "Adding sample performance data with correct field names...\n";

// Get existing employees
$employees = [];
$result = $conn->query("SELECT employee_id, name, department_name, position FROM employees WHERE status = 'active'");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
        echo "Found employee: {$row['name']} (ID: {$row['employee_id']})\n";
    }
} else {
    echo "No active employees found!\n";
    exit;
}

// Add sample goals
echo "\n🎯 Adding sample goals...\n";

$goals = [
    [$employees[0]['employee_id'], 'Complete Project Alpha', 'Lead the development of Project Alpha from start to finish', '2024-01-01', '2024-12-31', 'high', $employees[1]['employee_id'], 85],
    [$employees[1]['employee_id'], 'Improve Team Productivity', 'Implement new processes to improve team efficiency by 20%', '2024-03-01', '2024-09-30', 'medium', $employees[0]['employee_id'], 60],
    [$employees[2]['employee_id'], 'Increase Sales Revenue', 'Achieve 15% increase in quarterly sales revenue', '2024-01-01', '2024-03-31', 'high', $employees[1]['employee_id'], 75],
];

foreach ($goals as $i => $goal) {
    $stmt = $conn->prepare("INSERT INTO employee_goals (employee_id, goal_title, description, start_date, end_date, priority, assigned_by, progress_percentage, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'in_progress')");
    $stmt->bind_param("isssssid", $goal[0], $goal[1], $goal[2], $goal[3], $goal[4], $goal[5], $goal[6], $goal[7]);
    
    if ($stmt->execute()) {
        echo "✅ Added goal: {$goal[1]} for {$employees[$i]['name']}\n";
    } else {
        echo "❌ Failed to add goal: {$goal[1]} - " . $stmt->error . "\n";
    }
}

// Add sample performance reviews
echo "\n📝 Adding sample performance reviews...\n";

$reviews = [
    [$employees[0]['employee_id'], $employees[1]['employee_id'], '2024-01-01', '2024-03-31', 5, 5, 4, 5, 4, 4, 'Excellent performance in Q1. Exceeded all targets and showed great leadership.'],
    [$employees[1]['employee_id'], $employees[0]['employee_id'], '2024-01-01', '2024-03-31', 4, 4, 5, 4, 5, 3, 'Good performance with strong people management skills.'],
    [$employees[2]['employee_id'], $employees[1]['employee_id'], '2024-01-01', '2024-03-31', 4, 4, 4, 3, 4, 3, 'Solid performance with room for improvement in technical areas.']
];

foreach ($reviews as $i => $review) {
    $stmt = $conn->prepare("INSERT INTO hr_performance_reviews (employee_id, reviewer_id, review_period_start, review_period_end, overall_rating, goals_achievement, communication_skills, technical_skills, teamwork, leadership, comments, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
    $stmt->bind_param("iissiiiiiis", $review[0], $review[1], $review[2], $review[3], $review[4], $review[5], $review[6], $review[7], $review[8], $review[9], $review[10]);
    
    if ($stmt->execute()) {
        echo "✅ Added review for {$employees[$i]['name']}\n";
    } else {
        echo "❌ Failed to add review: " . $stmt->error . "\n";
    }
}

// Add sample performance metrics
echo "\n📊 Adding sample performance metrics...\n";

$metrics = [
    [$employees[0]['employee_id'], 'Code Quality Score', 85.5, 80.0, '%', 'Monthly', 'Quality', $employees[1]['employee_id'], 'Code review scores consistently above target'],
    [$employees[1]['employee_id'], 'Team Satisfaction', 90.0, 85.0, '%', 'Quarterly', 'Leadership', $employees[0]['employee_id'], 'High team satisfaction scores'],
    [$employees[2]['employee_id'], 'Task Completion Rate', 95.0, 90.0, '%', 'Monthly', 'Productivity', $employees[1]['employee_id'], 'Excellent task completion record']
];

foreach ($metrics as $i => $metric) {
    $stmt = $conn->prepare("INSERT INTO performance_metrics (employee_id, metric_name, metric_value, target_value, unit, measurement_period, metric_category, recorded_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isddsssis", $metric[0], $metric[1], $metric[2], $metric[3], $metric[4], $metric[5], $metric[6], $metric[7], $metric[8]);
    
    if ($stmt->execute()) {
        echo "✅ Added metric: {$metric[1]} for {$employees[$i]['name']}\n";
    } else {
        echo "❌ Failed to add metric: {$metric[1]} - " . $stmt->error . "\n";
    }
}

echo "\n🎉 Sample performance data added successfully!\n";
echo "\nYou can now test all features of the Performance Management system:\n";
echo "- Performance Reviews with detailed ratings\n";
echo "- Employee Goals with progress tracking\n";
echo "- Performance Metrics with achievement tracking\n";
echo "- Analytics dashboard with charts\n";

$conn->close();
?>
