<?php
// Email Configuration for Password Reset
// You can use PHPMailer or PHP's mail() function

function sendPasswordResetEmail($email, $reset_token, $username) {
    $reset_link = "http://localhost/billbook/reset_password.php?token=" . $reset_token;
    
    $subject = "Password Reset Request - BillBook";
    $message = "
    <html>
    <head>
        <title>Password Reset Request</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 5px 5px; }
            .button { display: inline-block; background: #007bff; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🔐 Password Reset Request</h2>
            </div>
            <div class='content'>
                <p>Hello <strong>" . htmlspecialchars($username) . "</strong>,</p>
                <p>We received a request to reset your password for your BillBook account.</p>
                <p>Click the button below to reset your password:</p>
                <p style='text-align: center;'>
                    <a href='" . $reset_link . "' class='button'>Reset Password</a>
                </p>
                <p>Or copy and paste this link in your browser:</p>
                <p><a href='" . $reset_link . "'>" . $reset_link . "</a></p>
                <p><strong>Note:</strong> This link will expire in 1 hour for security reasons.</p>
                <p>If you didn't request this password reset, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " BillBook - Invoice Management System</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: BillBook System <noreply@billbook.com>" . "\r\n";
    $headers .= "Reply-To: noreply@billbook.com" . "\r\n";
    
    // Send email using PHP's mail() function
    // Note: You may need to configure your server's SMTP settings
    if (mail($email, $subject, $message, $headers)) {
        return true;
    } else {
        return false;
    }
    
    /* 
    Alternative: Using PHPMailer (more reliable for production)
    Uncomment the code below and install PHPMailer via Composer if you want to use it:
    
    require_once 'vendor/autoload.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Set the SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com'; // SMTP username
        $mail->Password   = 'your-app-password';    // SMTP password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('noreply@billbook.com', 'BillBook System');
        $mail->addAddress($email, $username);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
    */
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
