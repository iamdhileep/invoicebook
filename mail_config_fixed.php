<?php
// Email Configuration for Password Reset
// Enhanced version with PHPMailer support and development fallback

require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Email configuration settings
$emailConfig = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => '', // Add your Gmail address here
    'smtp_password' => '', // Add your Gmail App Password here
    'from_email' => 'noreply@billbook.com',
    'from_name' => 'BillBook System',
    'use_phpmailer' => true, // Set to false to use PHP mail() function
    'development_mode' => true // Set to false in production
];

function sendPasswordResetEmail($email, $reset_token, $username) {
    global $emailConfig;
    
    $reset_link = "http://localhost/billbook/reset_password.php?token=" . $reset_token;
    
    $subject = "Password Reset Request - BillBook";
    $message = getEmailTemplate($username, $reset_link);
    
    // Try PHPMailer first, then fallback to development mode
    if ($emailConfig['use_phpmailer'] && !empty($emailConfig['smtp_username'])) {
        return sendEmailWithPHPMailer($email, $subject, $message, $username);
    } else {
        return sendEmailDevelopmentMode($email, $subject, $message, $reset_link, $username);
    }
}

function sendEmailWithPHPMailer($email, $subject, $message, $username) {
    global $emailConfig;
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $emailConfig['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $emailConfig['smtp_username'];
        $mail->Password   = $emailConfig['smtp_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $emailConfig['smtp_port'];
        
        // Recipients
        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($email, $username);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        
        // Fallback to development mode if PHPMailer fails
        global $emailConfig;
        if ($emailConfig['development_mode']) {
            return sendEmailDevelopmentMode($email, $subject, $message, '', $username);
        }
        return false;
    }
}

function sendEmailDevelopmentMode($email, $subject, $message, $reset_link, $username) {
    global $emailConfig;
    
    if ($emailConfig['development_mode']) {
        // Development mode: Log email content instead of sending
        $logContent = "
=== EMAIL WOULD BE SENT ===
To: $email ($username)
Subject: $subject
Reset Link: $reset_link
Time: " . date('Y-m-d H:i:s') . "
===========================
";
        
        error_log($logContent);
        
        // Also save to a file for easy access
        file_put_contents('email_log.txt', $logContent . "\n", FILE_APPEND | LOCK_EX);
        
        return true; // Return true in development mode
    }
    
    // Try PHP mail() function as last resort
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: {$emailConfig['from_name']} <{$emailConfig['from_email']}>" . "\r\n";
    
    return mail($email, $subject, $message, $headers);
}

function getEmailTemplate($username, $reset_link) {
    return "
    <html>
    <head>
        <title>Password Reset Request</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 25px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .button { display: inline-block; background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
            .button:hover { background: #0056b3; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            .warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 10px; border-radius: 4px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🔐 Password Reset Request</h2>
                <p style='margin: 0; opacity: 0.9;'>BillBook Invoice Management</p>
            </div>
            <div class='content'>
                <p>Hello <strong>" . htmlspecialchars($username) . "</strong>,</p>
                <p>We received a request to reset your password for your BillBook account. If you made this request, click the button below to set a new password:</p>
                
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='" . $reset_link . "' class='button'>🔑 Reset My Password</a>
                </div>
                
                <p>Or copy and paste this link into your web browser:</p>
                <p style='background: #e9ecef; padding: 10px; border-radius: 4px; word-break: break-all;'><a href='" . $reset_link . "'>" . $reset_link . "</a></p>
                
                <div class='warning'>
                    <strong>⚠️ Security Notice:</strong><br>
                    • This link will expire in 1 hour<br>
                    • If you didn't request this reset, please ignore this email<br>
                    • Your password will remain unchanged until you use this link
                </div>
                
                <p style='margin-top: 25px;'>Need help? Contact your system administrator.</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " BillBook - Secure Invoice Management System</p>
                <p>This is an automated message, please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

// Generate secure random token
function generateResetToken() {
    return bin2hex(random_bytes(32));
}

// Validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
?>
