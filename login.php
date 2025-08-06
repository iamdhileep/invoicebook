<?php
session_start();
include 'db.php';

// Redirect if already logged in
if (isset($_SESSION['admin']) || isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success_message = '';

// Check for logout success message
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $success_message = 'You have been successfully logged out.';
}

if (isset($_POST['username'], $_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // First try modern users table
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
            }
        }
        
        // Fallback to admin table (legacy system) - only if users table check failed
        if (empty($_SESSION['admin'])) {
            $query = "SELECT * FROM admin WHERE username='$username' AND password='$password'";
            $result = mysqli_query($conn, $query);

            if (mysqli_num_rows($result) === 1) {
                $admin = $result->fetch_assoc();
                // Set session variables for compatibility with both systems
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
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 Login - BillBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .card-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 40px 30px;
            text-align: center;
            border: none;
        }
        .card-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 2.2rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .card-header p {
            margin: 15px 0 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }
        .card-body {
            padding: 40px;
        }
        .form-floating {
            margin-bottom: 25px;
        }
        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 18px 15px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
            background: white;
        }
        .btn-login {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 12px;
            padding: 18px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: white;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3);
            color: white;
        }
        .forgot-password {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e9ecef;
        }
        .forgot-password a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .forgot-password a:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        .alert {
            border-radius: 12px;
            border: none;
            margin-bottom: 25px;
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .logo-container {
            background: rgba(255, 255, 255, 0.2);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }
        .logo-container i {
            font-size: 3rem;
        }
        .input-group-text {
            background: transparent;
            border: none;
            color: #6c757d;
        }
        .password-toggle {
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .password-toggle:hover {
            color: #007bff;
        }
        .features {
            background: #f8f9fa;
            padding: 20px;
            margin-top: 30px;
            border-radius: 12px;
            text-align: center;
        }
        .features h6 {
            color: #495057;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .features .row {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .features .col-4 {
            padding: 10px 5px;
        }
        .features i {
            display: block;
            font-size: 1.5rem;
            color: #007bff;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="card-header">
                <div class="logo-container">
                    <i class="fas fa-receipt"></i>
                </div>
                <h2>BillBook</h2>
                <p>Invoice Management System</p>
            </div>
            <div class="card-body">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <div class="form-floating">
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               placeholder="Enter your username"
                               value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                               required>
                        <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                    </div>

                    <div class="form-floating position-relative">
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password"
                               required>
                        <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                        <span class="position-absolute end-0 top-50 translate-middle-y me-3 password-toggle" 
                              onclick="togglePassword()" 
                              style="z-index: 10; cursor: pointer;">
                            <i class="fas fa-eye" id="passwordToggleIcon"></i>
                        </span>
                    </div>

                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </form>

                <div class="forgot-password">
                    <a href="forgot_password.php">
                        <i class="fas fa-key me-2"></i>Forgot Password?
                    </a>
                </div>

                <div class="features">
                    <h6>System Features</h6>
                    <div class="row text-center">
                        <div class="col-4">
                            <i class="fas fa-file-invoice"></i>
                            <div>Invoice Management</div>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-users"></i>
                            <div>Employee Tracking</div>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-chart-bar"></i>
                            <div>Reports & Analytics</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please fill in all fields.');
                return false;
            }
        });

        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Add loading state to login button
        document.getElementById('loginForm').addEventListener('submit', function() {
            const button = document.querySelector('.btn-login');
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
            button.disabled = true;
        });
    </script>
</body>
</html>