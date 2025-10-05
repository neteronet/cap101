<?php
session_start();

// Include the database connection file
include '../includes/connection.php'; // Make sure the path is correct

$error = '';    

if (isset($_POST['login'])) {
    $email = $_POST['username']; 
    $password = $_POST['password'];

    // Prepare a select statement
    $stmt = $conn->prepare("SELECT user_id, username, password_hash, user_type FROM users WHERE email = ? AND user_type = 'mao'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($user_id, $db_username, $password_hash, $user_type);
        $stmt->fetch();

        // Verify the password hash using SHA256
        if (hash('sha256', $password) === $password_hash) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $db_username;
            $_SESSION['user_type'] = $user_type;

            // Redirect to the municipal agriculturist dashboard
            header("location: municipal-dashboard.php"); // Assuming this is your dashboard for MAO
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Invalid email or password or you are not authorized to login here.";
    }
    $stmt->close();
}
// Close the connection when done with the script
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Agriculturist Login</title>

    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">

    <!-- FONT -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    <!-- icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        body {
            font-family: "Poppins", sans-serif;
            background: #f0f2f5; /* Light gray background */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .login-container {
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-width: 450px;
            width: 90%;
            text-align: center;
        }

        .login-container .logo {
            width: 120px;
            height: auto;
            margin-bottom: 1.5rem;
        }

        .login-container h1 {
            color: #19860f; /* Green for the title */
            font-weight: 700;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
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

        .back-to-home {
            color: #19860f;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .back-to-home:hover {
            color: #146c0b;
            text-decoration: underline;
        }

        .error-message {
            color: red;
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <main class="login-container">
        <form class="w-100" method="POST">
            <img class="logo" src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Official Seal">
            <h1 class="mb-4">MUNICIPAL AGRICULTURIST LOGIN</h1>

            <?php if (!empty($error)) : ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="form-floating mb-3">
                <input type="email" class="form-control" id="floatingInput" name="username"
                    placeholder="name@example.com" required>
                <label for="floatingInput">Email address</label>
            </div>

            <div class="form-floating mb-4">
                <input type="password" class="form-control" id="floatingPassword" name="password" placeholder="Password"
                    required>
                <label for="floatingPassword">Password</label>
            </div>

            <button class="btn btn-primary w-100 mb-3" type="submit" name="login">Login</button>

            <a href="../index.php" class="back-to-home">
                <i class="fas fa-arrow-left me-2"></i>Back to Home
            </a>
        </form>
    </main>

    <!-- BOOTSTRAP JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
        crossorigin="anonymous"></script>
</body>

</html>