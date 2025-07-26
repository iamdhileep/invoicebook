<?php
session_start();
include 'db.php';
include 'mail_config.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
        $message_type = 'danger';
    } elseif (!isValidEmail($email)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'danger';
    } else {
        // Check if email exists in users table
        $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $reset_token = generateResetToken();
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store reset token in database
            $stmt = $conn->prepare("INSERT INTO password_resets (user_id, email, token, expires_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), created_at = NOW()");
            $stmt->bind_param("isss", $user['id'], $email, $reset_token, $expires_at);
            
            if ($stmt->execute()) {
                // Send email
                if (sendPasswordResetEmail($email, $reset_token, $user['username'])) {
                    // Check if we're in development mode
                    include_once 'mail_config.php';
                    global $emailConfig;
                    
                    if ($emailConfig['development_mode']) {
                        $message = '<strong>Development Mode:</strong> Password reset link generated successfully!<br>';
                        $message .= '📧 Check the <code>email_log.txt</code> file for the reset link, or visit the ';
                        $message .= '<a href="email_setup_guide.php" class="alert-link"><i class="fas fa-cog"></i> Email Setup Guide</a> to view and test email functionality.';
                        $message_type = 'info';
                    } else {
                        $message = '<strong>Email Sent!</strong> Password reset instructions have been sent to your email address.';
                        $message_type = 'success';
                    }
                } else {
                    $message = '<strong>Email Error:</strong> Failed to send password reset email.<br>';
                    $message .= 'Please visit the <a href="email_setup_guide.php" class="alert-link"><i class="fas fa-tools"></i> Email Setup Guide</a> to configure email settings or contact your administrator.';
                    $message_type = 'warning';
                }
            } else {
                $message = 'Database error. Please try again.';
                $message_type = 'danger';
            }
        } else {
            // Don't reveal if email exists or not for security
            $message = 'If an account with that email exists, you will receive password reset instructions.';
            $message_type = 'info';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 Forgot Password - BillBook</title>
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
        .forgot-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        .card-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 30px;
            text-align: center;
            border: none;
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
        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .alert a {
            font-weight: 600;
            text-decoration: none;
        }
        .alert a:hover {
            text-decoration: underline;
        }
        .alert code {
            background: rgba(0,0,0,0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9em;
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
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
        }
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
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
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3);
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="forgot-card">
            <div class="card-header">
                <div class="icon-container">
                    <i class="fas fa-key"></i>
                </div>
                <h3>Forgot Password?</h3>
                <p>No worries! Enter your email and we'll send you reset instructions.</p>
            </div>
            <div class="card-body">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $message_type ?> d-flex align-items-center" role="alert">
                        <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'danger' ? 'fa-exclamation-triangle' : 'fa-info-circle') ?> me-2"></i>
                        <div><?= $message ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-floating">
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               placeholder="Enter your email address"
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                               required>
                        <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                    </button>
                </form>

                <div class="back-to-login">
                    <a href="login.php">
                        <i class="fas fa-arrow-left me-2"></i>Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
