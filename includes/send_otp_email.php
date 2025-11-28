<?php
/**
 * Send OTP Email Function
 * 
 * Based on your working PHPMailer implementation from Calderon_PracLongTest
 * Follows the exact same pattern that works in your previous project
 * 
 * @param string $email Recipient email address
 * @param string $otp 6-digit OTP code
 * @param string $user_name User's name (optional)
 * @return array ['success' => bool, 'message' => string]
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/email_config.php';

function sendOTPEmail($email, $otp, $user_name = 'User') {
    $config = getEmailConfig();
    
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Configuration - Following exact pattern from your working project
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['port'];
        $mail->SMTPOptions = $config['options'];

        // From / To - Following exact pattern from your working project
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($email, $user_name);

        // Email Subject
        $mail->Subject = 'Password Reset OTP - Province of Antique Agriculture Office';

        // Sanitize inputs for HTML
        $safeName = htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8');
        $safeOTP = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

        // HTML Email Body (Styled similar to your previous project)
        $body = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#111;line-height:1.6;max-width:600px;margin:0 auto">'
              . '<div style="background:linear-gradient(135deg,#19860f,#146c0b);padding:20px;border-radius:8px;color:#ffffff;text-align:center;margin:0 0 20px 0">'
              . '<div style="font-size:20px;font-weight:bold;letter-spacing:0.5px">Password Reset Request</div>'
              . '<div style="font-size:12px;opacity:0.9;margin-top:5px">Province of Antique - Agriculture Office</div>'
              . '</div>'
              
              . '<div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:20px 0">'
              . '<p style="margin:0 0 12px 0;font-size:16px"><strong>Hello ' . $safeName . ',</strong></p>'
              . '<p style="margin:0 0 16px 0">You have requested to reset your password. Please use the following One-Time Password (OTP) to verify your identity:</p>'
              
              . '<div style="background:#fff;border:2px dashed #19860f;border-radius:8px;padding:20px;text-align:center;margin:20px 0">'
              . '<div style="font-size:12px;color:#666;margin-bottom:8px">Your OTP Code:</div>'
              . '<div style="font-size:32px;font-weight:bold;color:#19860f;letter-spacing:8px;font-family:monospace">' . $safeOTP . '</div>'
              . '</div>'
              
              . '<div style="background:#fff3cd;border-left:4px solid #ffc107;padding:12px;margin:20px 0;border-radius:4px">'
              . '<p style="margin:0;font-size:13px;color:#856404"><strong>⚠️ Security Notice:</strong></p>'
              . '<ul style="margin:8px 0 0 20px;padding:0;font-size:13px;color:#856404">'
              . '<li>This OTP will expire in <strong>15 minutes</strong></li>'
              . '<li>Do not share this code with anyone</li>'
              . '<li>If you did not request this, please ignore this email</li>'
              . '</ul>'
              . '</div>'
              
              . '<p style="margin:20px 0 12px 0;font-size:14px">Enter this OTP on the password reset page to continue.</p>'
              . '<p style="margin:16px 0 0 0;font-size:13px;color:#666">If you have any questions or need assistance, please contact the Agriculture Office.</p>'
              . '</div>'
              
              . '<div style="text-align:center;margin-top:30px;padding-top:20px;border-top:1px solid #e5e7eb">'
              . '<p style="margin:0;font-size:12px;color:#999">This is an automated message. Please do not reply to this email.</p>'
              . '<p style="margin:8px 0 0 0;font-size:12px;color:#999">© ' . date('Y') . ' Province of Antique - Agriculture Office</p>'
              . '</div>'
              . '</div>';

        // Plain text alternative
        $altBody = "Password Reset Request\n\n"
                 . "Hello $user_name,\n\n"
                 . "You have requested to reset your password. Please use the following OTP to verify your identity:\n\n"
                 . "OTP Code: $otp\n\n"
                 . "⚠️ Security Notice:\n"
                 . "- This OTP will expire in 15 minutes\n"
                 . "- Do not share this code with anyone\n"
                 . "- If you did not request this, please ignore this email\n\n"
                 . "Enter this OTP on the password reset page to continue.\n\n"
                 . "If you have any questions, please contact the Agriculture Office.\n\n"
                 . "© " . date('Y') . " Province of Antique - Agriculture Office\n"
                 . "This is an automated message. Please do not reply.";

        $mail->isHTML(true);
        $mail->Body    = $body;
        $mail->AltBody = $altBody;

        // Send email
        $mail->send();
        
        return [
            'success' => true,
            'message' => 'OTP email sent successfully.'
        ];
        
    } catch (Exception $e) {
        $error_msg = $mail->ErrorInfo;
        error_log("PHPMailer Error for $email: " . $error_msg);
        
        // Provide more helpful error messages
        if (strpos($error_msg, 'SMTP connect()') !== false) {
            $user_msg = 'Failed to connect to email server. Please check your internet connection or contact administrator.';
        } elseif (strpos($error_msg, 'Authentication') !== false) {
            $user_msg = 'Email authentication failed. Please check email configuration.';
        } elseif (strpos($error_msg, 'Invalid address') !== false) {
            $user_msg = 'Invalid email address. Please check the email and try again.';
        } else {
            $user_msg = 'Failed to send OTP email. Please try again later or contact administrator.';
        }
        
        return [
            'success' => false,
            'message' => $user_msg
        ];
    }
}

?>

