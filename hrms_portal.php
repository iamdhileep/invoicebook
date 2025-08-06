<?php
session_start();
require_once 'db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $redirect = $_GET['redirect'] ?? 'dashboard.php';
    header("Location: " . $redirect);
    exit;
}

$error = '';
$success_message = '';

// Check for logout success message
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $success_message = 'You have been successfully logged out.';
}

// Handle login form submission
if (isset($_POST['username'], $_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Try modern users table first
        $users_check = $conn->query("SHOW TABLES LIKE 'users'");
        if ($users_check && $users_check->num_rows > 0) {
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password'])) {
                        // Set session variables for compatibility with both systems
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['admin'] = $user['username'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['user_role'] = $user['role']; // For HRMS compatibility
                        
                        // Handle redirect parameter
                        $redirect = $_GET['redirect'] ?? 'dashboard.php';
                        header("Location: " . $redirect);
                        exit;
                    } else {
                        $error = "Invalid username or password.";
                    }
                } else {
                    $error = "Invalid username or password.";
                }
                $stmt->close();
            }
        }
        
        // Fallback to admin table (legacy system) - only if users table check failed
        if (empty($_SESSION['admin'])) {
            $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ? AND password = ?");
            if ($stmt) {
                $stmt->bind_param("ss", $username, $password);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $admin = $result->fetch_assoc();
                    // Set session variables for compatibility
                    $_SESSION['admin'] = $username;
                    $_SESSION['user_id'] = 'admin_' . $admin['id'];
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = 'admin';
                    $_SESSION['user_role'] = 'admin'; // For HRMS compatibility
                    
                    // Handle redirect parameter
                    $redirect = $_GET['redirect'] ?? 'dashboard.php';
                    header("Location: " . $redirect);
                    exit;
                } else {
                    if (empty($error)) {
                        $error = "Invalid username or password.";
                    }
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS Portal - BillBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #6366f1;
            --success-color: #10b981;
            --gradient-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            background: var(--gradient-bg);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 450px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo-icon {
            background: var(--gradient-bg);
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .logo-icon i {
            font-size: 2rem;
            color: white;
        }
        
        .brand-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .brand-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
        }
        
        .btn-login {
            background: var(--gradient-bg);
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
            background: linear-gradient(135deg, #5b6cf0 0%, #8b5fa8 100%);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border-left: 4px solid #059669;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 2rem 0;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }
        
        .divider span {
            padding: 0 1rem;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .quick-access {
            background: rgba(79, 70, 229, 0.05);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1.5rem;
        }
        
        .quick-access h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .quick-access p {
            color: #6b7280;
            font-size: 0.875rem;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .login-container {
                margin: 1rem;
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-users-cog"></i>
            </div>
            <h1 class="brand-title">HRMS Portal</h1>
            <p class="brand-subtitle">Human Resource Management System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-floating">
                <input type="text" class="form-control" id="username" name="username" 
                       placeholder="Username" required autocomplete="username">
                <label for="username">
                    <i class="fas fa-user me-2"></i>Username
                </label>
            </div>

            <div class="form-floating">
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Password" required autocomplete="current-password">
                <label for="password">
                    <i class="fas fa-lock me-2"></i>Password
                </label>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt me-2"></i>
                Access HRMS Portal
            </button>
        </form>

        <div class="divider">
            <span>Secure Access</span>
        </div>

        <div class="quick-access">
            <h6><i class="fas fa-shield-alt me-2"></i>Secure Login</h6>
            <p>Your credentials are encrypted and secure. Contact your system administrator if you need assistance.</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus username field
        document.getElementById('username').focus();
        
        // Form enhancement
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = document.querySelector('.btn-login');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Authenticating...';
            btn.disabled = true;
        });
    </script>
</body>
</html>
