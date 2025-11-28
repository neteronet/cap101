<?php
/**
 * Test Email Configuration
 * 
 * This file tests if PHPMailer and email configuration are working correctly
 * 
 * Usage: http://localhost/cap101-main/test_email.php
 * 
 * ⚠️ Make sure to configure includes/email_config.php first!
 */

// Check if PHPMailer is installed
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die('❌ PHPMailer not found. Please run setup_phpmailer.php first.');
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/email_config.php';
require __DIR__ . '/includes/send_otp_email.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Email Configuration</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #28a745; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; padding: 15px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; margin: 10px 0; }
        form { margin-top: 20px; }
        input[type="email"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #19860f; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #146c0b; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Test Email Configuration</h1>
        
        <?php
        // Check configuration
        $config = getEmailConfig();
        $config_ok = true;
        
        if ($config['username'] === 'your-email@gmail.com' || empty($config['username'])) {
            echo '<div class="warning">⚠️ Email configuration not set. Please edit includes/email_config.php</div>';
            $config_ok = false;
        }
        
        if ($config['password'] === 'your-app-password' || empty($config['password'])) {
            echo '<div class="warning">⚠️ Email password not set. Please edit includes/email_config.php</div>';
            $config_ok = false;
        }
        
        if ($config_ok) {
            echo '<div class="info">✅ Email configuration found:</div>';
            echo '<ul>';
            echo '<li><strong>SMTP Host:</strong> ' . htmlspecialchars($config['host']) . '</li>';
            echo '<li><strong>SMTP Port:</strong> ' . htmlspecialchars($config['port']) . '</li>';
            echo '<li><strong>From Email:</strong> ' . htmlspecialchars($config['from_email']) . '</li>';
            echo '<li><strong>From Name:</strong> ' . htmlspecialchars($config['from_name']) . '</li>';
            echo '</ul>';
        }
        
        // Handle test email
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email']) && $config_ok) {
            $test_email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
            
            if ($test_email) {
                echo '<div class="info">📤 Sending test email to: ' . htmlspecialchars($test_email) . '</div>';
                
                $test_otp = '123456'; // Test OTP
                $result = sendOTPEmail($test_email, $test_otp, 'Test User');
                
                if ($result['success']) {
                    echo '<div class="success">✅ Email sent successfully! Check your inbox (and spam folder).</div>';
                    echo '<div class="info">Test OTP Code: <strong>' . $test_otp . '</strong></div>';
                } else {
                    echo '<div class="error">❌ Failed to send email: ' . htmlspecialchars($result['message']) . '</div>';
                    echo '<div class="info">Check PHP error logs for more details.</div>';
                }
            } else {
                echo '<div class="error">❌ Invalid email address.</div>';
            }
        }
        ?>
        
        <?php if ($config_ok): ?>
        <form method="POST">
            <h3>Send Test Email</h3>
            <label>Enter your email address to receive a test OTP:</label>
            <input type="email" name="email" placeholder="your-email@example.com" required>
            <button type="submit" name="test_email">Send Test Email</button>
        </form>
        <?php else: ?>
        <div class="info">
            <h3>Setup Instructions:</h3>
            <ol>
                <li>Open <code>includes/email_config.php</code></li>
                <li>Set your Gmail address in <code>SMTP_USERNAME</code> and <code>SMTP_FROM_EMAIL</code></li>
                <li>Get Gmail App Password from <a href="https://myaccount.google.com/apppasswords" target="_blank">Google App Passwords</a></li>
                <li>Set the App Password in <code>SMTP_PASSWORD</code></li>
                <li>Refresh this page and test again</li>
            </ol>
        </div>
        <?php endif; ?>
        
        <hr>
        <p><a href="pages/forgot-password.php">← Back to Forgot Password</a></p>
    </div>
</body>
</html>


