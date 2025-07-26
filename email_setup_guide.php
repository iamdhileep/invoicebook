<?php
// Email Setup Guide and Configuration Helper
// This file helps you configure email settings for password reset functionality

include 'auth_check.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>📧 Email Setup Guide - BillBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .setup-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .setup-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .step-card {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            margin: 20px 0;
            padding: 20px;
            border-radius: 0 8px 8px 0;
        }
        .code-block {
            background: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
            overflow-x: auto;
        }
        .alert-custom {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .btn-custom {
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1><i class="fas fa-envelope-open-text"></i> Email Setup Guide</h1>
            <p class="mb-0">Configure email settings for password reset functionality</p>
        </div>
        
        <div class="p-4">
            <div class="alert alert-info alert-custom">
                <h5><i class="fas fa-info-circle"></i> Current Status</h5>
                <p class="mb-0">Your system is currently in <strong>Development Mode</strong>. Password reset emails will be logged to files instead of being sent.</p>
            </div>

            <!-- Quick Test Section -->
            <div class="step-card">
                <h4><i class="fas fa-play-circle text-success"></i> Quick Test</h4>
                <p>Test the current email configuration:</p>
                
                <?php
                if ($_POST['test_email'] ?? false) {
                    include_once 'mail_config.php';
                    $test_email = $_POST['email'] ?? 'test@example.com';
                    $test_token = generateResetToken();
                    
                    echo "<div class='alert alert-info'>";
                    echo "<strong>Testing email to: " . htmlspecialchars($test_email) . "</strong><br>";
                    
                    if (sendPasswordResetEmail($test_email, $test_token, 'Test User')) {
                        echo "<i class='fas fa-check-circle text-success'></i> Email test successful! ";
                        echo "Check the 'email_log.txt' file in your project directory for the email content.";
                    } else {
                        echo "<i class='fas fa-times-circle text-danger'></i> Email test failed.";
                    }
                    echo "</div>";
                }
                ?>
                
                <form method="POST" class="row g-3">
                    <div class="col-md-8">
                        <input type="email" class="form-control" name="email" placeholder="Enter test email address" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="test_email" class="btn btn-success btn-custom">
                            <i class="fas fa-paper-plane"></i> Test Email
                        </button>
                    </div>
                </form>
            </div>

            <!-- Step 1: Development Mode -->
            <div class="step-card">
                <h4><i class="fas fa-laptop-code text-primary"></i> Step 1: Development Mode (Current)</h4>
                <p>For local development, the system logs email content instead of sending real emails.</p>
                <ul>
                    <li>Password reset emails are logged to <code>email_log.txt</code></li>
                    <li>Reset links are fully functional for testing</li>
                    <li>No actual email configuration needed</li>
                </ul>
                <div class="alert alert-success alert-custom">
                    <strong>✅ This mode is perfect for development and testing!</strong>
                </div>
            </div>

            <!-- Step 2: Gmail Setup -->
            <div class="step-card">
                <h4><i class="fab fa-google text-danger"></i> Step 2: Production Gmail Setup</h4>
                <p>To send real emails in production, follow these steps:</p>
                
                <h6>2.1. Create Gmail App Password</h6>
                <ol>
                    <li>Go to your Google Account settings</li>
                    <li>Enable 2-Factor Authentication</li>
                    <li>Go to Security → App passwords</li>
                    <li>Generate an app password for "Mail"</li>
                    <li>Copy the 16-character password</li>
                </ol>

                <h6>2.2. Update mail_config.php</h6>
                <p>Edit the email configuration in <code>mail_config.php</code>:</p>
                <div class="code-block">
$emailConfig = [<br>
&nbsp;&nbsp;&nbsp;&nbsp;'smtp_username' => 'your-gmail@gmail.com', // Your Gmail address<br>
&nbsp;&nbsp;&nbsp;&nbsp;'smtp_password' => 'your-16-char-app-password', // Gmail App Password<br>
&nbsp;&nbsp;&nbsp;&nbsp;'development_mode' => false, // Set to false for production<br>
&nbsp;&nbsp;&nbsp;&nbsp;'use_phpmailer' => true<br>
];
                </div>
            </div>

            <!-- Step 3: Alternative Options -->
            <div class="step-card">
                <h4><i class="fas fa-cogs text-warning"></i> Step 3: Alternative Email Options</h4>
                
                <h6>Option A: Other SMTP Providers</h6>
                <ul>
                    <li><strong>Outlook/Hotmail:</strong> smtp-mail.outlook.com:587</li>
                    <li><strong>Yahoo:</strong> smtp.mail.yahoo.com:587</li>
                    <li><strong>Custom SMTP:</strong> Use your hosting provider's SMTP settings</li>
                </ul>

                <h6>Option B: Local SMTP Server</h6>
                <p>For advanced users, you can set up a local SMTP server like:</p>
                <ul>
                    <li>Mercury Mail Server</li>
                    <li>hMailServer</li>
                    <li>Postfix (Linux)</li>
                </ul>
            </div>

            <!-- Current Configuration Display -->
            <div class="step-card">
                <h4><i class="fas fa-list-alt text-info"></i> Current Configuration</h4>
                <?php
                include_once 'mail_config.php';
                echo "<div class='row'>";
                echo "<div class='col-md-6'>";
                echo "<strong>Development Mode:</strong> " . ($emailConfig['development_mode'] ? 'Enabled' : 'Disabled') . "<br>";
                echo "<strong>PHPMailer:</strong> " . ($emailConfig['use_phpmailer'] ? 'Enabled' : 'Disabled') . "<br>";
                echo "<strong>SMTP Host:</strong> " . $emailConfig['smtp_host'] . "<br>";
                echo "</div>";
                echo "<div class='col-md-6'>";
                echo "<strong>SMTP Port:</strong> " . $emailConfig['smtp_port'] . "<br>";
                echo "<strong>From Email:</strong> " . $emailConfig['from_email'] . "<br>";
                echo "<strong>Username Set:</strong> " . (!empty($emailConfig['smtp_username']) ? 'Yes' : 'No') . "<br>";
                echo "</div>";
                echo "</div>";
                ?>
            </div>

            <!-- Log File Section -->
            <div class="step-card">
                <h4><i class="fas fa-file-alt text-secondary"></i> Email Log</h4>
                <p>In development mode, email content is logged to <code>email_log.txt</code>:</p>
                
                <?php
                $log_file = 'email_log.txt';
                if (file_exists($log_file)) {
                    $log_content = file_get_contents($log_file);
                    if (!empty($log_content)) {
                        echo "<div class='code-block' style='max-height: 300px; overflow-y: auto;'>";
                        echo nl2br(htmlspecialchars($log_content));
                        echo "</div>";
                        
                        echo "<form method='POST' style='margin-top: 10px;'>";
                        echo "<button type='submit' name='clear_log' class='btn btn-warning btn-sm'>";
                        echo "<i class='fas fa-trash'></i> Clear Log</button>";
                        echo "</form>";
                    } else {
                        echo "<p class='text-muted'>Log file is empty.</p>";
                    }
                } else {
                    echo "<p class='text-muted'>No log file found yet. Send a test email to create it.</p>";
                }

                if ($_POST['clear_log'] ?? false) {
                    file_put_contents($log_file, '');
                    echo "<div class='alert alert-success alert-custom mt-2'>Log file cleared!</div>";
                    echo "<script>setTimeout(() => location.reload(), 1000);</script>";
                }
                ?>
            </div>

            <div class="text-center mt-4">
                <a href="dashboard.php" class="btn btn-primary btn-custom">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="forgot_password.php" class="btn btn-success btn-custom">
                    <i class="fas fa-key"></i> Test Password Reset
                </a>
            </div>
        </div>
    </div>
</body>
</html>
