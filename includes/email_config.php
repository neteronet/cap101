<?php
/**
 * Email Configuration
 * 
 * Based on your working PHPMailer implementation from Calderon_PracLongTest
 * Follows the exact same pattern that works in your previous project
 * 
 * ⚠️ SECURITY: Update these with your actual email credentials
 */

// Prevent multiple includes
if (defined('SMTP_HOST')) {
    return; // Already included
}

// Email Configuration - Update these with your actual Gmail credentials
// 
// HOW TO GET THESE VALUES:
// 1. SMTP_USERNAME & SMTP_FROM_EMAIL: Your Gmail address (e.g., 'yourname@gmail.com')
// 2. SMTP_PASSWORD: Gmail App Password (NOT your regular password!)
//    - Go to: https://myaccount.google.com/apppasswords
//    - Enable 2-Step Verification first (if not enabled)
//    - Generate App Password for "Mail" → "Other (Custom name)" → "CAP101 System"
//    - Copy the 16-character password (remove spaces)
//    - Example: 'abcd efgh ijkl mnop' → Use as 'abcdefghijklmnop'
//
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'calderonkurt@sac.edu.ph'); // Your Gmail address
define('SMTP_PASSWORD', 'ixcejmkpddvyuien');    // Gmail App Password (16 chars, NO SPACES - removed spaces)
define('SMTP_FROM_EMAIL', 'calderonkurt@sac.edu.ph'); // From email (should match username)
define('SMTP_FROM_NAME', 'Province of Antique - Agriculture Office');

// SMTP Options - Same as your working project
define('SMTP_OPTIONS', [ 'ssl' => ['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true] ]);

/**
 * Get Email Configuration Array
 * Returns configuration in the same format as your working project
 * 
 * @return array Email configuration
 */
if (!function_exists('getEmailConfig')) {
    function getEmailConfig() {
        return [
            'host' => SMTP_HOST,
            'port' => SMTP_PORT,
            'username' => SMTP_USERNAME,
            'password' => SMTP_PASSWORD,
            'from_email' => SMTP_FROM_EMAIL,
            'from_name' => SMTP_FROM_NAME,
            'options' => SMTP_OPTIONS
        ];
    }
}

/**
 * Instructions for Gmail App Password:
 * 
 * 1. Go to your Google Account: https://myaccount.google.com/
 * 2. Click "Security" in the left sidebar
 * 3. Under "Signing in to Google", enable "2-Step Verification" if not already enabled
 * 4. After enabling 2-Step Verification, go back to Security
 * 5. Under "Signing in to Google", click "App passwords"
 * 6. Select "Mail" and "Other (Custom name)" - enter "CAP101 System"
 * 7. Click "Generate"
 * 8. Copy the 16-character password (no spaces)
 * 9. Paste it in SMTP_PASSWORD above
 * 
 * ⚠️ IMPORTANT: Use the App Password, NOT your regular Gmail password!
 */

?>

