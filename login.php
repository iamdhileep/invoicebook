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
    <title>Login - Business Management System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Professional Color System */
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary-color: #64748b;
            --success-color: #059669;
            --danger-color: #dc2626;
            
            /* Professional Neutral Colors */
            --white: #ffffff;
            --gray-25: #fcfcfd;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            
            /* Professional Background Gradients */
            --bg-gradient-primary: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            --bg-gradient-subtle: linear-gradient(135deg, var(--gray-25) 0%, var(--gray-50) 100%);
            --bg-gradient-card: linear-gradient(135deg, var(--white) 0%, var(--gray-25) 100%);
            
            /* Professional Shadow System */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-base: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            
            /* Border Radius */
            --radius-sm: 4px;
            --radius-base: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            
            /* Typography */
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            
            /* Transitions */
            --transition-fast: all 0.15s ease;
            --transition-base: all 0.2s ease;
            --transition-slow: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background: var(--bg-gradient-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow: hidden;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            animation: float 20s infinite linear;
            pointer-events: none;
        }

        @keyframes float {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
        }

        .login-card {
            background: var(--bg-gradient-card);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            overflow: hidden;
            transition: var(--transition-base);
        }

        .login-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);
        }

        .login-header {
            text-align: center;
            padding: 3rem 2.5rem 1.5rem;
            background: var(--bg-gradient-subtle);
            border-bottom: 1px solid var(--gray-100);
        }

        .logo-container {
            width: 80px;
            height: 80px;
            background: var(--bg-gradient-primary);
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
            position: relative;
        }

        .logo-container::before {
            content: '';
            position: absolute;
            inset: -2px;
            background: var(--bg-gradient-primary);
            border-radius: 50%;
            z-index: -1;
            filter: blur(8px);
            opacity: 0.6;
        }

        .logo-container i {
            color: var(--white);
            font-size: 2rem;
        }

        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .login-subtitle {
            color: var(--gray-600);
            font-size: 0.95rem;
            font-weight: 400;
        }

        .login-form {
            padding: 2rem 2.5rem 2.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            letter-spacing: 0.025em;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-family: var(--font-family);
            background: var(--white);
            transition: var(--transition-base);
            position: relative;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            transform: translateY(-1px);
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 1.1rem;
            z-index: 5;
        }

        .form-control.with-icon {
            padding-left: 2.75rem;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            font-size: 1.1rem;
            z-index: 5;
            transition: var(--transition-fast);
        }

        .password-toggle:hover {
            color: var(--gray-600);
        }

        .login-btn {
            width: 100%;
            padding: 0.875rem 1.5rem;
            background: var(--bg-gradient-primary);
            color: var(--white);
            border: none;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 600;
            font-family: var(--font-family);
            cursor: pointer;
            transition: var(--transition-base);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: var(--transition-slow);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .forgot-password {
            text-align: center;
            margin-top: 1.5rem;
        }

        .forgot-password a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition-fast);
        }

        .forgot-password a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--danger-color);
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .setup-notice {
            background: rgba(37, 99, 235, 0.1);
            border: 1px solid rgba(37, 99, 235, 0.2);
            color: var(--primary-color);
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            margin-top: 1rem;
            text-align: center;
        }

        .setup-notice a {
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: 600;
        }

        .setup-notice a:hover {
            text-decoration: underline;
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--white);
            animation: spin 1s ease-in-out infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
                margin: 0;
            }
            
            .login-header,
            .login-form {
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
        }

        /* Focus trap for accessibility */
        .login-card:focus-within {
            box-shadow: var(--shadow-xl), 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-container">
                    <i class="bi bi-building"></i>
                </div>
                <h1 class="login-title">Welcome Back</h1>
                <p class="login-subtitle">Sign in to your Business Management System</p>
            </div>
            
            <form method="POST" class="login-form" id="loginForm">
                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <i class="bi bi-person input-icon"></i>
                        <input 
                            type="text" 
                            name="username" 
                            id="username"
                            class="form-control with-icon" 
                            placeholder="Enter your username"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            required 
                            autocomplete="username"
                            autofocus
                        />
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <i class="bi bi-lock input-icon"></i>
                        <input 
                            type="password" 
                            name="password" 
                            id="password"
                            class="form-control with-icon" 
                            placeholder="Enter your password"
                            required 
                            autocomplete="current-password"
                        />
                        <button type="button" class="password-toggle" id="passwordToggle" aria-label="Toggle password visibility">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="login-btn" id="loginBtn">
                    <div class="loading-spinner" id="loadingSpinner"></div>
                    <span id="loginBtnText">Sign In</span>
                </button>
                
                <div class="forgot-password">
                    <a href="#" onclick="alert('Please contact your administrator to reset your password.')">Forgot your password?</a>
                </div>
                
                <div class="setup-notice">
                    <i class="bi bi-info-circle"></i>
                    First time setup? <a href="setup_user_system.php">Initialize User System</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password toggle functionality
        document.getElementById('passwordToggle').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        });

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('loginBtn');
            const spinner = document.getElementById('loadingSpinner');
            const btnText = document.getElementById('loginBtnText');
            
            submitBtn.disabled = true;
            spinner.style.display = 'inline-block';
            btnText.textContent = 'Signing In...';
        });

        // Enhanced form validation
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('invalid', function(e) {
                e.preventDefault();
                this.style.borderColor = 'var(--danger-color)';
                this.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.1)';
            });
            
            input.addEventListener('input', function() {
                if (this.checkValidity()) {
                    this.style.borderColor = 'var(--gray-200)';
                    this.style.boxShadow = 'none';
                }
            });
        });

        // Keyboard navigation enhancement
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
                const form = document.getElementById('loginForm');
                if (form.checkValidity()) {
                    form.submit();
                }
            }
        });

        // Auto-focus on page load
        window.addEventListener('load', function() {
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>