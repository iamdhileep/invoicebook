<?php
session_start();
include 'db.php';

$username = $_POST['username'];
$password = hash('sha256', $_POST['password']);

$sql = "SELECT * FROM users WHERE username = ? AND password = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $_SESSION['user'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    header("Location: dashboard.php");
} else {
    $_SESSION['error'] = "Invalid login credentials.";
    header("Location: login.php");
}
