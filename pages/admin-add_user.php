<?php
session_start();
include '../includes/connection.php';

// --- IMPROVEMENT 1: Robust Connection Check ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? "Connection object not set"));
    // Redirect or show a maintenance page
    header("location: database_error.php"); 
    exit();
}

// --- Check if the user is logged in ---
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: admin-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Admin'; // Default fallback
$is_admin = false; // Flag for explicit admin check

// Fetch admin's name AND user_type (IMPROVEMENT 2: Fetch user_type for security)
$stmt_name = $conn->prepare("SELECT name, user_type FROM users WHERE user_id = ?");
if ($stmt_name) {
    $stmt_name->bind_param("i", $user_id);
    $stmt_name->execute();
    $stmt_name->bind_result($db_name, $db_user_type);
    $stmt_name->fetch();
    $stmt_name->close();

    if ($db_name) {
        $display_name = htmlspecialchars($db_name);
    }

    // --- IMPROVEMENT 3: Explicit Admin Authorization Check ---
    if ($db_user_type === 'admin') {
        $is_admin = true;
    } else {
        // If not admin, destroy session and redirect
        session_destroy();
        header("location: admin-login.php");
        exit();
    }
} else {
    error_log("Failed to prepare statement for user name/type: " . $conn->error);
}

// --- IMPROVEMENT 4: Handle PRG Pattern for Session Messages ---
$message = '';
$message_type = ''; 
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    // Clear the session messages so they don't reappear on refresh
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Security check - already done above, but good to be defensive
    if (!$is_admin) {
        // Should not happen, but safe to check again
        header("location: admin-login.php");
        exit();
    }

    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $name = trim($_POST['name']);
    $user_type_new = $_POST['user_type'];

    // Basic validation
    if (empty($username) || empty($password) || empty($name) || empty($user_type_new)) {
        $message = "All fields are required.";
        $message_type = 'danger';
    } elseif (!in_array($user_type_new, ['farmer', 'mao', 'admin'])) {
        $message = "Invalid user type selected.";
        $message_type = 'danger';
    } else {
        // Check if username already exists
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $message = "Username already exists. Please choose a different one.";
            $message_type = 'danger';
        } else {
            // Hash the password using SHA256 as requested (Note: password_hash() is recommended)
            $password_hash = hash('sha256', $password);

            // --- Generate unique code ---
            $characters = '123456789abcdefghijklmnopqrstuvwxyz';
            $charactersLength = strlen($characters);
            $code_length = 8; // adjust length as needed

            do {
                $generated_code = '';
                for ($i = 0; $i < $code_length; $i++) {
                    $generated_code .= $characters[random_int(0, $charactersLength - 1)];
                }

                // Check for uniqueness in the database
                $stmt_code_check = $conn->prepare("SELECT user_id FROM users WHERE generated_code = ?");
                $stmt_code_check->bind_param("s", $generated_code);
                $stmt_code_check->execute();
                $stmt_code_check->store_result();
                $code_exists = $stmt_code_check->num_rows > 0;
                $stmt_code_check->close();
            } while ($code_exists);
            // --- End unique code generation ---

            // Insert new user into the database, including generated_code
            $stmt_insert = $conn->prepare("INSERT INTO users (username, password_hash, name, user_type, generated_code) VALUES (?, ?, ?, ?, ?)");
            if ($stmt_insert) {
                $stmt_insert->bind_param("sssss", $username, $password_hash, $name, $user_type_new, $generated_code);
                if ($stmt_insert->execute()) {
                    // --- PRG Redirect on Success ---
                    $_SESSION['message'] = "User '" . htmlspecialchars($name) . "' added successfully as '" . htmlspecialchars($user_type_new) . "'. Code: " . $generated_code;
                    $_SESSION['message_type'] = 'success';
                    $stmt_insert->close();
                    $stmt_check->close();
                    $conn->close();
                    header("location: admin-add_user.php"); 
                    exit();
                } else {
                    $message = "Error adding user: " . $stmt_insert->error;
                    $message_type = 'danger';
                }
                $stmt_insert->close();
            } else {
                $message = "Database error: Could not prepare statement to add user.";
                $message_type = 'danger';
                error_log("Failed to prepare statement for adding user: " . $conn->error);
            }
        }
        $stmt_check->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin - Add User</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Custom Styles (copy from your existing admin-dashboard.php or link a separate CSS) -->
    <style>
        body {
            font-family: "Poppins", sans-serif;
            background: #f8f9fa;
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            margin: 0;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: #19860f;
            padding: 1rem 0;
            overflow-y: auto;
            font-size: 14px;
            z-index: 1050;
            border-right: 1px solid #ddd;
        }

        .sidebar .nav-link {
            color: #fff;
            padding: 0.6rem 1rem;
            width: 100%;
            box-sizing: border-box;
            border-radius: 0;
            display: flex;
            align-items: center;
            text-decoration: none;
        }


        .sidebar .nav-link i {
            margin-right: 8px;
            font-size: 1rem;
        }

        .sidebar .nav-link.active {
            background-color: #fff;
            color: #19860f;
            font-weight: 600;
        }

        .sidebar .nav-link:hover:not(.active) {
            background-color: #146c0b;
            color: #fff;
        }

        .sidebar .header-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            margin-bottom: 1rem;
        }

        .sidebar .header-brand img {
            width: 100%;
            max-width: 120px;
            height: auto;
            background: #19860f;
            padding: 5px;
            border-radius: 4px;
        }

        .sidebar .header-brand div {
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            text-align: center;
            margin-top: 6px;
        }

        .card-header-custom {
            position: fixed;
            top: 0;
            left: 250px;
            right: 0;
            height: 56px;
            background-color: #fff;
            color: #19860f;
            padding: 0 1.25rem;
            font-weight: 500;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1060;
            border-bottom: 1px solid #ddd;
        }

        .header-brand span {
            font-size: 1rem;
            font-weight: 600;
            color: #19860f;
        }

        .logout-btn {
            background: #ff4b2b;
            color: #fff;
            border: none;
            padding: 6px 14px;
            font-size: 14px;
            border-radius: 20px;
            transition: background 0.2s ease;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: #e04325;
        }

        .btn-theme {
            background-color: #19860f;
            color: #fff;
            font-size: 15px;
            padding: 10px 20px;
            border-radius: 4px;
        }

        .btn-theme:hover {
            background-color: #146c0b;
        }

        main {
            margin-left: 250px;
            padding: 1rem 2rem 2rem 2rem;
            padding-top: 72px;
            background: #f8f9fa;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #19860f;
            margin-bottom: 1rem;
        }

        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
        }

        .card-title {
            color: #19860f;
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <nav class="sidebar">
        <a href="ProvincialAgriHome.html" class="header-brand">
            <img src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Province of Antique" />
            <div>Province of Antique</div>
        </a>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="admin-dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-add_user.php" class="nav-link active">
                    <i class="fas fa-user-plus"></i> Add User
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-view_farmers.php" class="nav-link">
                    <i class="fas fa-users"></i> View Farmers
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-register_farmer.php" class="nav-link">
                    <i class="fas fa-clipboard-list"></i> Register Farmer Details
                </a>
            </li>
        </ul>
    </nav>

    <!-- Header -->
    <div class="card-header card-header-custom d-flex justify-content-end align-items-center">
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <button class="logout-btn" onclick="location.href='admin-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h1 class="page-title">Add New User</h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">User Details</h5>
                    <form action="admin-add_user.php" method="POST">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required />
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username"
                                placeholder="name@example.com" />
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required />
                        </div>
                        <div class="mb-3">
                            <label for="user_type" class="form-label">User Type</label>
                            <select class="form-select" id="user_type" name="user_type" required>
                                <option value="">Select User Type</option>
                                <option value="farmer">Farmer</option>
                                <option value="mao">MAO</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-theme">Add User</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
