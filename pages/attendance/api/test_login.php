<?php
session_start();
header('Content-Type: application/json');

// Simple test login endpoint for testing purposes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    // Simple test credentials (for testing only)
    if ($username === 'test' && $password === 'test123') {
        $_SESSION['admin'] = [
            'id' => 1,
            'username' => 'test',
            'name' => 'Test Admin',
            'login_time' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => $_SESSION['admin']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid credentials. Use username: test, password: test123'
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check login status
    if (isset($_SESSION['admin'])) {
        echo json_encode([
            'success' => true,
            'message' => 'Already logged in',
            'user' => $_SESSION['admin']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Not logged in'
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Logout
    session_destroy();
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}
?>
