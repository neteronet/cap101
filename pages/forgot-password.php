<?php
/**
 * Forgot Password - Step 1: Request OTP
 * 
 * User enters their email/username to receive OTP
 */

session_start();
include '../includes/connection.php';

$error = '';
$success = '';
$verify_link = 'verify-otp.php?sent=1';

// Reset previous OTP session state when a new request starts
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['reset_email'], $_SESSION['reset_user_type'], $_SESSION['otp_ready']);
}

// Check if PHPMailer is available
$phpmailer_available = file_exists(__DIR__ . '/../vendor/autoload.php');
if (!$phpmailer_available) {
    $error = 'PHPMailer is not installed. Please run setup_phpmailer.php first.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_otp']) && $phpmailer_available) {
    $email_input = trim($_POST['email'] ?? '');
    
    if (empty($email_input)) {
        $error = 'Please enter your email address.';
    } else {
        // Check if email exists in database (for any user type) - Matching your system's user lookup
        $stmt = $conn->prepare("SELECT user_id, username, name, user_type FROM users WHERE username = ?");
        $stmt->bind_param("s", $email_input);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $user_id = $user['user_id'];
            $email = $user['username'];
            $user_name = $user['name'] ?? 'User';
            $user_type = $user['user_type'];
            
            // Rate limiting: Check if user has requested OTP more than 3 times in the last hour
            $stmt_rate = $conn->prepare("
                SELECT COUNT(*) as request_count 
                FROM password_reset_tokens 
                WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) AND used = 0
            ");
            $stmt_rate->bind_param("s", $email);
            $stmt_rate->execute();
            $rate_result = $stmt_rate->get_result();
            $rate_data = $rate_result->fetch_assoc();
            $stmt_rate->close();
            
            if ($rate_data['request_count'] >= 3) {
                $error = 'Too many OTP requests. Please wait 1 hour before requesting again.';
            } else {
                // Generate 6-digit OTP
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                
                // Store OTP in database (expires in 15 minutes)
                $otp_hash = hash('sha256', $otp); // Hash for security
                $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                $stmt_insert = $conn->prepare("
                    INSERT INTO password_reset_tokens (user_id, email, otp_hash, expires_at, used, attempts) 
                    VALUES (?, ?, ?, ?, 0, 0)
                ");
                $stmt_insert->bind_param("isss", $user_id, $email, $otp_hash, $expires_at);
                
                if ($stmt_insert->execute()) {
                    // Send OTP via email - Following your working PHPMailer pattern
                    require '../includes/send_otp_email.php';
                    $email_result = sendOTPEmail($email, $otp, $user_name);
                    
                    if ($email_result['success']) {
                        // Store email in session for next step
                        $_SESSION['reset_email'] = $email;
                        $_SESSION['reset_user_type'] = $user_type;
                        $_SESSION['otp_ready'] = true;
                        
                        // Close database connection before redirect
                        $stmt_insert->close();
                        $conn->close();
                        
                        // Redirect to verify OTP page
                        header("Location: verify-otp.php?sent=1");
                        exit();
                    } else {
                        $error = $email_result['message'] . ' Please check your email configuration or try again later.';
                        error_log("Email sending failed for $email: " . $email_result['message']);
                    }
                } else {
                    $error = 'Failed to generate OTP. Please try again.';
                    error_log("Failed to insert OTP: " . $stmt_insert->error);
                }
                if (isset($stmt_insert)) {
                    $stmt_insert->close();
                }
            }
        } else {
            // Provide clear feedback so users can correct their email
            $error = 'No account found using that email address. Please double-check and try again.';
        }
        $stmt->close();
    }
}

// Don't close connection here if we might redirect
if (isset($conn) && !headers_sent()) {
    // Connection will be closed in verify-otp.php if needed
}

if (!empty($_SESSION['reset_email'])) {
    $verify_link .= '&email=' . urlencode($_SESSION['reset_email']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Request OTP</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: "Poppins", sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .reset-container {
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-width: 450px;
            width: 90%;
        }

        .reset-container .logo {
            width: 120px;
            height: auto;
            margin: 0 auto 1.5rem;
            display: block;
        }

        .reset-container h1 {
            color: #19860f;
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
            text-align: center;
        }

        .reset-container .subtitle {
            color: #6c757d;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .form-floating .form-control {
            border-radius: 0.5rem;
            border: 1px solid #ced4da;
            padding: 1rem 1.25rem;
            height: auto;
        }

        .form-floating label {
            padding: 1rem 1.25rem;
            color: #6c757d;
        }

        .form-floating .form-control:focus {
            border-color: #19860f;
            box-shadow: 0 0 0 0.25rem rgba(25, 134, 15, 0.25);
        }

        .btn-primary {
            background-color: #19860f;
            border-color: #19860f;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #146c0b;
            border-color: #146c0b;
        }

        .back-to-login {
            color: #19860f;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s ease;
            display: inline-block;
            margin-top: 1rem;
        }

        .back-to-login:hover {
            color: #146c0b;
            text-decoration: underline;
        }

        .error-message {
            color: #dc3545;
            font-size: 0.95rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 0.5rem;
        }

        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #004085;
        }

        .info-box i {
            margin-right: 8px;
        }
    </style>
</head>

<body>
    <main class="reset-container">
        <img class="logo" src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Official Seal">
        <h1>Forgot Password?</h1>
        <p class="subtitle">Enter your email to receive a One-Time Password (OTP)</p>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <strong>How it works:</strong> We'll send a 6-digit OTP to your email. The OTP expires in 15 minutes.
        </div>

        <?php if (!empty($error)) : ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)) : ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <div class="mt-3">
                    <a href="<?php echo htmlspecialchars($verify_link); ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-key me-2"></i>Enter OTP Code
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php 
        // Check if user has email in session (OTP was sent)
        if (!empty($_SESSION['otp_ready']) && !empty($_SESSION['reset_email'])) : 
        ?>
            <div class="alert alert-info" role="alert">
                <i class="fas fa-envelope me-2"></i>
                <strong>OTP Sent!</strong> Check your email for the 6-digit code.
                <div class="mt-3">
                    <a href="<?php echo htmlspecialchars($verify_link); ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-key me-2"></i>Enter OTP Code
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" id="forgotPasswordForm">
            <div class="form-floating mb-3">
                <input type="email" class="form-control" id="floatingEmail" name="email" 
                       placeholder="name@example.com" required autocomplete="email">
                <label for="floatingEmail"><i class="fas fa-envelope me-2"></i>Email Address</label>
            </div>

            <button class="btn btn-primary w-100 mb-3" type="submit" name="request_otp">
                <i class="fas fa-paper-plane me-2"></i>Send OTP
            </button>

            <div class="text-center">
                <a href="farmers-login.php" class="back-to-login">
                    <i class="fas fa-arrow-left me-2"></i>Back to Login
                </a>
            </div>
        </form>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            const email = document.getElementById('floatingEmail').value.trim();
            if (!email) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Email Required',
                    text: 'Please enter your email address.'
                });
            }
        });
    </script>
</body>
</html>
