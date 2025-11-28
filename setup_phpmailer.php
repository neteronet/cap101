<?php
/**
 * PHPMailer Setup Script
 * 
 * This script helps set up PHPMailer by copying from your previous project
 * OR installing via Composer
 * 
 * Run this file once: http://localhost/cap101-main/setup_phpmailer.php
 */

$source_vendor = 'C:\xampp\htdocs\FILES HERE\Calderon_PracLongTest\vendor';
$target_vendor = __DIR__ . '\vendor';
$composer_json = __DIR__ . '\composer.json';

?>
<!DOCTYPE html>
<html>
<head>
    <title>PHPMailer Setup</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 PHPMailer Setup</h1>
        
        <?php
        // Check if vendor already exists
        if (file_exists($target_vendor . '\autoload.php')) {
            echo '<div class="success">✅ PHPMailer is already installed! Vendor folder exists.</div>';
            echo '<p><a href="pages/forgot-password.php">Go to Forgot Password Page</a></p>';
        } else {
            echo '<div class="info">📦 PHPMailer not found. Setting up...</div>';
            
            // Method 1: Copy from previous project
            if (is_dir($source_vendor)) {
                echo '<p>📂 Found previous project vendor folder. Copying...</p>';
                
                if (!is_dir($target_vendor)) {
                    mkdir($target_vendor, 0777, true);
                }
                
                // Copy vendor folder
                $copied = false;
                if (is_dir($source_vendor)) {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($source_vendor, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    
                    foreach ($iterator as $item) {
                        $target = $target_vendor . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                        
                        if ($item->isDir()) {
                            if (!is_dir($target)) {
                                mkdir($target, 0777, true);
                            }
                        } else {
                            copy($item, $target);
                        }
                    }
                    $copied = true;
                }
                
                if ($copied && file_exists($target_vendor . '\autoload.php')) {
                    echo '<div class="success">✅ Successfully copied vendor folder from previous project!</div>';
                    echo '<p><a href="pages/forgot-password.php">Go to Forgot Password Page</a></p>';
                } else {
                    echo '<div class="error">❌ Failed to copy vendor folder. Please install via Composer.</div>';
                }
            } else {
                // Method 2: Create composer.json and install
                echo '<div class="info">📝 Previous project vendor folder not found. Creating composer.json...</div>';
                
                $composer_content = json_encode([
                    'require' => [
                        'phpmailer/phpmailer' => '^6.11'
                    ]
                ], JSON_PRETTY_PRINT);
                
                file_put_contents($composer_json, $composer_content);
                
                echo '<div class="info">✅ Created composer.json</div>';
                echo '<p><strong>Next steps:</strong></p>';
                echo '<ol>';
                echo '<li>Open Command Prompt</li>';
                echo '<li>Navigate to: <code>' . __DIR__ . '</code></li>';
                echo '<li>Run: <code>composer install</code></li>';
                echo '<li>Refresh this page to verify installation</li>';
                echo '</ol>';
                echo '<pre>cd "' . __DIR__ . '"
composer install</pre>';
            }
        }
        ?>
        
        <hr>
        <h2>📋 Setup Checklist</h2>
        <ul>
            <li><?php echo file_exists($target_vendor . '\autoload.php') ? '✅' : '❌'; ?> PHPMailer installed</li>
            <li><?php echo file_exists('database/password_reset_tokens_table.sql') ? '✅' : '❌'; ?> Database schema file exists</li>
            <li><?php echo file_exists('includes/email_config.php') ? '✅' : '❌'; ?> Email config file exists</li>
            <li><?php 
                $config = file_get_contents('includes/email_config.php');
                echo (strpos($config, 'your-email@gmail.com') === false) ? '✅' : '⚠️'; 
            ?> Email credentials configured</li>
        </ul>
        
        <div class="info">
            <strong>⚠️ Important:</strong> After PHPMailer is installed, you must:
            <ol>
                <li>Run the database SQL script: <code>database/password_reset_tokens_table.sql</code></li>
                <li>Configure email credentials in <code>includes/email_config.php</code></li>
                <li>Get Gmail App Password from: <a href="https://myaccount.google.com/apppasswords" target="_blank">Google App Passwords</a></li>
            </ol>
        </div>
    </div>
</body>
</html>


