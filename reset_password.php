<?php
session_start();
include 'db.php';

$token = $_GET['token'] ?? '';
$message = '';
$message_type = '';
$valid_token = false;
$user_data = null;

// Validate token
if (!empty($token)) {
    $stmt = $conn->prepare("SELECT pr.*, u.username FROM password_resets pr JOIN users u ON pr.user_id = u.id WHERE pr.token = ? AND pr.expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $valid_token = true;
        $user_data = $result->fetch_assoc();
    } else {
        $message = 'Invalid or expired reset token. Please request a new password reset.';
        $message_type = 'danger';
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $message = 'Please fill in both password fields.';
        $message_type = 'danger';
    } elseif (strlen($new_password) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'danger';
    } else {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_data['user_id']);
        
        if ($stmt->execute()) {
            // Delete used reset token
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            
            $message = 'Password successfully reset! You can now log in with your new password.';
            $message_type = 'success';
            $valid_token = false; // Hide form after successful reset
        } else {
            $message = 'Failed to update password. Please try again.';
            $message_type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 Reset Password - BillBook</title>
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
        .reset-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        .card-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 30px;
            text-align: center;
            border: none;
        }
        .card-header.error {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        .card-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.8rem;
        }
        .card-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }
        .card-body {
            padding: 40px;
        }
        .form-floating {
            margin-bottom: 20px;
        }
        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 15px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.15);
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }
        .back-to-login {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e9ecef;
        }
        .back-to-login a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .back-to-login a:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        .alert {
            border-radius: 12px;
            border: none;
            margin-bottom: 25px;
        }
        .icon-container {
            background: rgba(255, 255, 255, 0.2);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .icon-container i {
            font-size: 2.5rem;
        }
        .password-requirements {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="reset-card">
            <div class="card-header <?= $valid_token ? '' : 'error' ?>">
                <div class="icon-container">
                    <i class="fas <?= $valid_token ? 'fa-lock' : 'fa-exclamation-triangle' ?>"></i>
                </div>
                <h3><?= $valid_token ? 'Reset Password' : 'Invalid Token' ?></h3>
                <p><?= $valid_token ? 'Enter your new password below' : 'The reset link is invalid or expired' ?></p>
            </div>
            <div class="card-body">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $message_type ?> d-flex align-items-center" role="alert">
                        <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> me-2"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($valid_token): ?>
                    <div class="password-requirements">
                        <strong><i class="fas fa-info-circle me-2"></i>Password Requirements:</strong>
                        <ul>
                            <li>At least 6 characters long</li>
                            <li>Use a mix of letters, numbers, and symbols</li>
                            <li>Avoid common passwords</li>
                        </ul>
                    </div>

                    <form method="POST" action="" id="resetForm">
                        <div class="form-floating">
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Enter new password"
                                   minlength="6"
                                   required>
                            <label for="password"><i class="fas fa-lock me-2"></i>New Password</label>
                        </div>

                        <div class="form-floating">
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Confirm new password"
                                   minlength="6"
                                   required>
                            <label for="confirm_password"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Reset Password
                        </button>
                    </form>
                <?php endif; ?>

                <div class="back-to-login">
                    <a href="login.php">
                        <i class="fas fa-arrow-left me-2"></i>Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });

        // Real-time password confirmation check
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
