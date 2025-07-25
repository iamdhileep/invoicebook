<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin'])) {
    header('Location: dashboard.php');
    exit;
}

include 'db.php';
include 'models/UserPermission.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        $userPermission = new UserPermission($conn);
        $user_id = $userPermission->authenticate($username, $password);
        
        if ($user_id) {
            // Get user info
            $user = $userPermission->getUserInfo($user_id);
            
            // Set session
            $_SESSION['admin'] = $user_id;
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['permissions'] = explode(',', $user['permissions']);
            
            // Set remember me cookie if requested
            if ($remember_me) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (30 * 24 * 60 * 60); // 30 days
                
                // Store token in database
                $stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $stmt->bind_param('si', $token, $user_id);
                $stmt->execute();
                
                // Set cookie
                setcookie('remember_token', $token, $expires, '/');
            }
            
            // Log the login
            $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, ip_address, user_agent, created_at) VALUES (?, 'login', ?, ?, NOW())");
            $ip = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $stmt->bind_param('iss', $user_id, $ip, $user_agent);
            $stmt->execute();
            
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

// Check for remember me cookie
if (isset($_COOKIE['remember_token']) && !isset($_SESSION['admin'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT id, username, role, permissions FROM users WHERE remember_token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user) {
        $_SESSION['admin'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['permissions'] = explode(',', $user['permissions']);
        
        header('Location: dashboard.php');
        exit;
    } else {
        // Invalid token, clear cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BillBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        
        .login-form {
            padding: 3rem;
        }
        
        .login-image {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            text-align: center;
            padding: 3rem;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .btn-login {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            color: white;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .logo-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="row g-0">
                <!-- Login Image Side -->
                <div class="col-lg-6 login-image">
                    <div>
                        <i class="bi bi-receipt logo-icon"></i>
                        <h2 class="mb-3">Welcome to BillBook</h2>
                        <p class="mb-0">Advanced Business Management System</p>
                        <p class="small">Manage employees, attendance, invoices and more with role-based permissions</p>
                    </div>
                </div>
                
                <!-- Login Form Side -->
                <div class="col-lg-6">
                    <div class="login-form">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold text-dark">Sign In</h3>
                            <p class="text-muted">Enter your credentials to access your account</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-2"></i><?= $success ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="loginForm">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="Username or Email" required 
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                                <label for="username">
                                    <i class="bi bi-person me-2"></i>Username or Email
                                </label>
                            </div>
                            
                            <div class="form-floating">
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Password" required>
                                <label for="password">
                                    <i class="bi bi-lock me-2"></i>Password
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                                <label class="form-check-label" for="remember_me">
                                    Remember me for 30 days
                                </label>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-login btn-lg">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <small class="text-muted">
                                Need help? Contact your system administrator
                            </small>
                        </div>
                        
                        <!-- Demo Users Info -->
                        <div class="mt-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Demo Credentials</h6>
                                </div>
                                <div class="card-body small">
                                    <div class="row">
                                        <div class="col-12">
                                            <strong>Admin:</strong> admin / admin123<br>
                                            <strong>User:</strong> user / user123<br>
                                            <strong>Manager:</strong> manager / manager123
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus username field
        document.getElementById('username').focus();
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please enter both username and password.');
                return false;
            }
        });
        
        // Show/hide password toggle
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleBtn = document.getElementById('togglePassword');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleBtn.innerHTML = '<i class="bi bi-eye-slash"></i>';
            } else {
                passwordField.type = 'password';
                toggleBtn.innerHTML = '<i class="bi bi-eye"></i>';
            }
        }
    </script>
</body>
</html>
