<?php
/**
 * Verify OTP - Step 2: Verify OTP Code
 * 
 * User enters OTP received via email
 */

session_start();
include '../includes/connection.php';

$error = '';
$email = $_SESSION['reset_email'] ?? '';

// Check if user came from redirect with sent parameter
$otp_sent = isset($_GET['sent']) && $_GET['sent'] == '1';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $otp_input = trim($_POST['otp'] ?? '');
    
    if (empty($otp_input) || strlen($otp_input) !== 6 || !ctype_digit($otp_input)) {
        $error = 'Please enter a valid 6-digit OTP.';
    } else {
        // Hash the input OTP
        $otp_hash = hash('sha256', $otp_input);

        // SIMPLIFIED, ROBUST LOGIC:
        // Always validate against the latest unused OTP for this email
        // 1) Find the latest token for the email in session
        if (!empty($email)) {
            $stmt = $conn->prepare("
                SELECT id, user_id, email, otp_hash, attempts, expires_at 
                FROM password_reset_tokens 
                WHERE email = ? AND used = 0 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = false;
        }
        
        if ($result && $result->num_rows === 1) {
            $token_data = $result->fetch_assoc();
            $token_id   = $token_data['id'];
            $user_id    = $token_data['user_id'];
            $email      = $token_data['email'];
            $_SESSION['reset_email'] = $email;

            // Check expiration
            if (strtotime($token_data['expires_at']) <= time()) {
                $error = 'Your OTP has expired. Please request a new one.';
            }
            // Check attempts (max 5 attempts)
            elseif ($token_data['attempts'] >= 5) {
                $error = 'Too many failed attempts. Please request a new OTP.';
            }
            // Check hash match
            elseif (!hash_equals($token_data['otp_hash'], $otp_hash)) {
                // Increment attempts
                $stmt_attempt = $conn->prepare("
                    UPDATE password_reset_tokens 
                    SET attempts = attempts + 1 
                    WHERE id = ?
                ");
                $stmt_attempt->bind_param("i", $token_id);
                $stmt_attempt->execute();
                $stmt_attempt->close();

                $error = 'Invalid OTP. Please check the code in your email and try again.';
            } else {
                // OTP is valid: mark used
                $stmt_update = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?");
                $stmt_update->bind_param("i", $token_id);
                $stmt_update->execute();
                $stmt_update->close();
                
                // Create session token for password reset
                $reset_token = bin2hex(random_bytes(32));
                $_SESSION['reset_token']     = $reset_token;
                $_SESSION['reset_user_id']   = $user_id;
                $_SESSION['reset_email']     = $email;
                unset($_SESSION['otp_ready']);

                // Robust redirect to reset password page
                echo '<!DOCTYPE html><html><head>';
                echo '<meta http-equiv="refresh" content="0;url=reset-password.php">';
                echo '<script>window.location.href="reset-password.php";</script>';
                echo '</head><body></body></html>';
                exit();
            }
        } else {
            $error = 'No active OTP found for this email. Please request a new OTP.';
        }

        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

// Check if OTP was sent (from redirect)
$otp_sent = isset($_GET['sent']) && $_GET['sent'] == '1';

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Password Reset</title>

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

        .verify-container {
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-width: 450px;
            width: 90%;
        }

        .verify-container .logo {
            width: 120px;
            height: auto;
            margin: 0 auto 1.5rem;
            display: block;
        }

        .verify-container h1 {
            color: #19860f;
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
            text-align: center;
        }

        .verify-container .subtitle {
            color: #6c757d;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .otp-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #ced4da;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }

        .otp-input:focus {
            border-color: #19860f;
            box-shadow: 0 0 0 0.25rem rgba(25, 134, 15, 0.25);
            outline: none;
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

        .back-link {
            color: #19860f;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s ease;
            display: inline-block;
            margin-top: 1rem;
        }

        .back-link:hover {
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

        .success-message {
            color: #155724;
            font-size: 0.95rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 0.5rem;
        }

        .email-display {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
            color: #004085;
        }
    </style>
</head>

<body>
    <main class="verify-container">
        <img class="logo" src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Official Seal">
        <h1>Verify OTP</h1>
        <p class="subtitle">Enter the 6-digit code sent to your email</p>

        <?php if (!empty($email)) : ?>
        <div class="email-display">
            <i class="fas fa-envelope me-2"></i>
            <strong>Email:</strong> <?php echo htmlspecialchars($email); ?>
        </div>
        <?php else : ?>
        <div class="alert alert-warning" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Note:</strong> Please enter the OTP code you received in your email.
        </div>
        <?php endif; ?>

        <?php if ($otp_sent) : ?>
            <div class="success-message">
                <i class="fas fa-check-circle me-2"></i>
                OTP has been sent to your email. Please check your inbox.
            </div>
        <?php endif; ?>

        <?php if (!empty($error)) : ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="verifyOTPForm">
            <div class="mb-3">
                <label for="otp" class="form-label text-center d-block mb-3">
                    <strong>Enter OTP Code:</strong>
                </label>
                <input type="text" 
                       class="form-control text-center" 
                       id="otp" 
                       name="otp" 
                       placeholder="000000" 
                       maxlength="6" 
                       pattern="[0-9]{6}"
                       required 
                       autocomplete="off"
                       style="font-size: 24px; font-weight: bold; letter-spacing: 8px; height: 60px;">
            </div>

            <button class="btn btn-primary w-100 mb-3" type="submit" name="verify_otp">
                <i class="fas fa-check me-2"></i>Verify OTP
            </button>

            <div class="text-center">
                <a href="forgot-password.php" class="back-link">
                    <i class="fas fa-arrow-left me-2"></i>Request New OTP
                </a>
            </div>
        </form>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Keep OTP numeric-only and let user click the button manually
        document.getElementById('otp').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Focus on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('otp').focus();
        });
    </script>
</body>
</html>
