<?php
session_start();
include 'db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Check if users table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'users'");
        if ($table_check->num_rows > 0) {
            // Check users table first (new system)
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password'])) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Update last login time
                        $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        if ($update_stmt) {
                            $update_stmt->bind_param("i", $user['id']);
                            $update_stmt->execute();
                        }
                        
                        header("Location: dashboard.php");
                        exit;
                    } else {
                        $error = "Invalid password.";
                    }
                } else {
                    // Fallback to old admin table
                    $admin_check = $conn->query("SHOW TABLES LIKE 'admin'");
                    if ($admin_check->num_rows > 0) {
                        $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ? AND password = ?");
                        if ($stmt) {
                            $stmt->bind_param("ss", $username, $password);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows === 1) {
                                $admin = $result->fetch_assoc();
                                $_SESSION['user_id'] = 'admin_' . $admin['id'];
                                $_SESSION['username'] = $admin['username'];
                                $_SESSION['role'] = 'admin';
                                header("Location: dashboard.php");
                                exit;
                            } else {
                                $error = "Invalid username or password.";
                            }
                        } else {
                            $error = "Database error. Please contact administrator.";
                        }
                    } else {
                        $error = "Invalid username or password.";
                    }
                }
                $stmt->close();
            } else {
                $error = "Database error. Please contact administrator.";
            }
        } else {
            // Users table doesn't exist, check admin table
            $admin_check = $conn->query("SHOW TABLES LIKE 'admin'");
            if ($admin_check->num_rows > 0) {
                $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ? AND password = ?");
                if ($stmt) {
                    $stmt->bind_param("ss", $username, $password);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 1) {
                        $admin = $result->fetch_assoc();
                        $_SESSION['user_id'] = 'admin_' . $admin['id'];
                        $_SESSION['username'] = $admin['username'];
                        $_SESSION['role'] = 'admin';
                        header("Location: dashboard.php");
                        exit;
                    } else {
                        $error = "Invalid username or password.";
                    }
                    $stmt->close();
                } else {
                    $error = "Database error. Please contact administrator.";
                }
            } else {
                $error = "System not properly configured. Please run setup_user_system.php first.";
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
    <title>BillBook - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .input-group-text {
            background: transparent;
            border: 2px solid #e9ecef;
            border-right: none;
        }
        .input-group .form-control {
            border-left: none;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .setup-link {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
        }
        .setup-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .setup-link a:hover {
            color: #764ba2;
        }
    </style>
</head>
<body class="d-flex justify-content-center align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-container">
                    <div class="login-header">
                        <h2 class="mb-0"><i class="fas fa-book-open me-2"></i>BillBook</h2>
                        <p class="mb-0 mt-2 opacity-75">Sign in to your account</p>
                    </div>
                    
                    <div class="p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="username" 
                                           name="username" 
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                           required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           required>
                                    <button class="btn btn-outline-secondary" 
                                            type="button" 
                                            id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-login text-white w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                            
                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Demo credentials: admin / admin123
                                </small>
                            </div>
                        </form>
                        
                        <!-- Setup link for first-time users -->
                        <div class="setup-link">
                            <small class="text-muted">
                                <i class="fas fa-cog me-1"></i>
                                First time setup? 
                                <a href="setup_user_system.php">Initialize User System</a>
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <small class="text-white opacity-75">
                        &copy; 2024 BillBook. All rights reserved.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>
</html>