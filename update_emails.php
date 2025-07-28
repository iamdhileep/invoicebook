<?php
require_once 'db.php';

$updates = [
    ['id' => 23, 'email' => 'sdk@company.com'],
    ['id' => 24, 'email' => 'dhileepkumar@company.com'],
    ['id' => 26, 'email' => 'pp@company.com'],
    ['id' => 28, 'email' => 'dj@company.com']
];

foreach ($updates as $update) {
    $stmt = $conn->prepare('UPDATE employees SET email = ? WHERE employee_id = ?');
    $stmt->bind_param('si', $update['email'], $update['id']);
    if ($stmt->execute()) {
        echo 'Updated employee ' . $update['id'] . ' with email ' . $update['email'] . "\n";
    } else {
        echo 'Failed to update employee ' . $update['id'] . "\n";
    }
}

echo "\nEmaili update completed.\n";
?>
