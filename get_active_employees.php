<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include 'db.php';

$date = $_GET['date'] ?? date('Y-m-d');

// Get count of employees who are present today
$query = "
    SELECT COUNT(DISTINCT a.employee_id) as count
    FROM attendance a 
    JOIN employees e ON a.employee_id = e.employee_id 
    WHERE a.attendance_date = ? 
    AND a.status = 'Present'
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

$count = 0;
if ($result && $row = $result->fetch_assoc()) {
    $count = $row['count'] ?? 0;
}

header('Content-Type: application/json');
echo json_encode(['count' => $count]);
?>