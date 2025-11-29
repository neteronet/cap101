<?php
/**
 * Reset Password - Step 3: Set New Password
 * 
 * User sets a new password after OTP verification
 */

session_start();
include '../includes/connection.php';

$error = '';
$success = '';

// Check if user has valid reset token
$reset_token = $_SESSION['reset_token'] ?? '';
$user_id = $_SESSION['reset_user_id'] ?? null;
$email = $_SESSION['reset_email'] ?? '';

// Redirect if no valid reset session
if (empty($reset_token) || empty($user_id) || empty($email)) {
    header("Location: forgot-password.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please enter and confirm your new password.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = 'Password must contain at least one number.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match. Please try again.';
    } else {
        // Hash the new password using SHA256 - Matching your exact system process
        $password_hash = hash('sha256', $new_password);
        
        // Update password in database
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->bind_param("si", $password_hash, $user_id);
        
        if ($stmt->execute()) {
            // Invalidate all OTP tokens for this user
            $stmt_invalidate = $conn->prepare("
                UPDATE password_reset_tokens 
                SET used = 1 
                WHERE user_id = ? AND used = 0
            ");
            $stmt_invalidate->bind_param("i", $user_id);
            $stmt_invalidate->execute();
            $stmt_invalidate->close();
            
            // Clear reset session
            unset($_SESSION['reset_token']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_email']);
            
            // Determine redirect based on user type - Matching your system's login pages
            $user_type = $_SESSION['reset_user_type'] ?? 'farmer';
            $login_page = 'farmers-login.php'; // Default for farmer
            if ($user_type === 'mao') {
                $login_page = 'municipal-login.php';
            } elseif ($user_type === 'admin') {
                $login_page = 'admin-login.php';
            }
            
            // Redirect to login with success message
            header("Location: $login_page?password_reset=1");
            exit();
        } else {
            $error = 'Failed to update password. Please try again.';
            error_log("Failed to update password: " . $stmt->error);
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>

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

        .error-message {
            color: #dc3545;
            font-size: 0.95rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 0.5rem;
        }

        .password-requirements {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            color: #004085;
        }

        .password-requirements ul {
            margin: 0.5rem 0 0 1.5rem;
            padding: 0;
        }

        .password-requirements li {
            margin: 0.25rem 0;
        }

        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 0.5rem;
            transition: all 0.3s ease;
        }

        .strength-weak { background: #dc3545; width: 33%; }
        .strength-medium { background: #ffc107; width: 66%; }
        .strength-strong { background: #28a745; width: 100%; }
    </style>
</head>

<body>
    <main class="reset-container">
        <img class="logo" src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Official Seal">
        <h1>Recover Account</h1>
        <p class="subtitle">Enter your new password to recover your account</p>

        <div class="password-requirements">
            <strong><i class="fas fa-info-circle me-2"></i>Password Requirements:</strong>
            <ul>
                <li>At least 8 characters long</li>
                <li>Contains at least one uppercase letter</li>
                <li>Contains at least one lowercase letter</li>
                <li>Contains at least one number</li>
            </ul>
        </div>

        <?php if (!empty($error)) : ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="resetPasswordForm">
            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="new_password" name="new_password" 
                       placeholder="New Password" required autocomplete="new-password">
                <label for="new_password"><i class="fas fa-lock me-2"></i>New Password</label>
                <div class="password-strength" id="passwordStrength"></div>
            </div>

            <div class="form-floating mb-4">
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                       placeholder="Retype New Password" required autocomplete="new-password">
                <label for="confirm_password"><i class="fas fa-lock me-2"></i>Retype New Password</label>
                <small id="passwordMatch" class="text-muted" style="display:none;"></small>
            </div>

            <button class="btn btn-primary w-100 mb-3" type="submit" name="reset_password">
                <i class="fas fa-key me-2"></i>Recover Account
            </button>
        </form>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordStrength = document.getElementById('passwordStrength');
        const passwordMatch = document.getElementById('passwordMatch');
        const form = document.getElementById('resetPasswordForm');

        // Password strength indicator
        newPassword.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;

            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            passwordStrength.className = 'password-strength';
            if (password.length === 0) {
                passwordStrength.style.display = 'none';
            } else {
                passwordStrength.style.display = 'block';
                if (strength <= 2) {
                    passwordStrength.classList.add('strength-weak');
                } else if (strength <= 4) {
                    passwordStrength.classList.add('strength-medium');
                } else {
                    passwordStrength.classList.add('strength-strong');
                }
            }
        });

        // Password match indicator
        confirmPassword.addEventListener('input', function() {
            if (this.value.length > 0) {
                passwordMatch.style.display = 'block';
                if (this.value === newPassword.value) {
                    passwordMatch.textContent = '✓ Passwords match';
                    passwordMatch.className = 'text-success';
                } else {
                    passwordMatch.textContent = '✗ Passwords do not match';
                    passwordMatch.className = 'text-danger';
                }
            } else {
                passwordMatch.style.display = 'none';
            }
        });

        // Form validation
        form.addEventListener('submit', function(e) {
            const password = newPassword.value;
            const confirm = confirmPassword.value;

            if (password.length < 8) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Password Too Short',
                    text: 'Password must be at least 8 characters long.'
                });
                return;
            }

            if (!/[A-Z]/.test(password)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Password Requirement',
                    text: 'Password must contain at least one uppercase letter.'
                });
                return;
            }

            if (!/[a-z]/.test(password)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Password Requirement',
                    text: 'Password must contain at least one lowercase letter.'
                });
                return;
            }

            if (!/[0-9]/.test(password)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Password Requirement',
                    text: 'Password must contain at least one number.'
                });
                return;
            }

            if (password !== confirm) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Passwords Do Not Match',
                    text: 'Please make sure both passwords are the same.'
                });
                return;
            }
        });
    </script>
</body>
</html>