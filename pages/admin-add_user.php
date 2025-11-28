<?php
session_start();

// The connection must be open at the start
// Assuming the path is correct: '../includes/connection.php'
include '../includes/connection.php'; 

// --- IMPROVEMENT 1: Robust Connection Check ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? "Connection object not set"));
    // Redirect or show a maintenance page
    header("location: database_error.php"); 
    exit();
}

// --- Check if the user is logged in and is admin ---
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

// Store POST values to repopulate the form in case of an error
$post_values = [
    'username' => '', 'password' => '', 'name' => '', 'user_type' => '',
    'rsbsa_id' => '', 'first_name' => '', 'middle_name' => '', 'last_name' => '', 
    'address' => '', 'contact_number' => '', 'age' => '', 'gender' => '', 
    'civil_status' => '', 'land_location' => '', 'land_size' => '', 'main_crop' => ''
];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Security check - already done above, but good to be defensive
    if (!$is_admin) {
        header("location: admin-login.php");
        exit();
    }

    // Sanitize and collect core user data
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $user_type_new = $_POST['user_type'] ?? '';

    // Update post values for repopulation
    $post_values['username'] = $username;
    $post_values['name'] = $name;
    $post_values['user_type'] = $user_type_new;

    // Basic validation
    if (empty($username) || empty($password) || empty($name) || empty($user_type_new)) {
        $message = "All core fields (Username, Password, Full Name, User Type) are required.";
        $message_type = 'danger';
    } elseif (!in_array($user_type_new, ['farmer', 'mao', 'admin'])) {
        $message = "Invalid user type selected.";
        $message_type = 'danger';
    } else {
        $valid = true;
        // --- Farmer Specific Data Collection & Validation ---
        if ($user_type_new === 'farmer') {
            // Collect and sanitize farmer specific data
            $rsbsa_id = trim($_POST['rsbsa_id'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $contact_number = trim($_POST['contact_number'] ?? '');
            $age_input = trim($_POST['age'] ?? ''); // Use temporary variable for validation
            $gender = $_POST['gender'] ?? '';
            $civil_status = $_POST['civil_status'] ?? '';
            $land_location = trim($_POST['land_location'] ?? '');
            $land_size = trim($_POST['land_size'] ?? '');
            $main_crop = trim($_POST['main_crop'] ?? '');

            // Convert age to integer
            $age = filter_var($age_input, FILTER_VALIDATE_INT);

            // Update post values for repopulation
            $post_values = array_merge($post_values, compact(
                'rsbsa_id', 'first_name', 'middle_name', 'last_name', 'address', 
                'contact_number', 'age', 'gender', 'civil_status', 'land_location', 
                'land_size', 'main_crop'
            ));

            // Required fields check for farmer
            if (empty($rsbsa_id) || empty($first_name) || empty($last_name) || empty($address) || 
                empty($contact_number) || $age === false || empty($gender) || empty($civil_status) ||
                empty($land_location) || empty($land_size) || empty($main_crop)) {
                $message = "All farmer details fields (marked with *) are required and Age must be a valid number.";
                $message_type = 'danger';
                $valid = false;
            }

            // Additional type checks (basic)
            if ($valid && ($age < 18)) {
                 $message = "Age must be at least 18.";
                 $message_type = 'danger';
                 $valid = false;
            }
        }
        // --- End Farmer Specific Data Collection & Validation ---

        if ($valid) {
            // Check if username already exists
            $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt_check->bind_param("s", $username);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $message = "Username already exists. Please choose a different one.";
                $message_type = 'danger';
            } else {
                // Check if RSBSA ID already exists (Only for farmers)
                if ($user_type_new === 'farmer') {
                     $stmt_rsbsa_check = $conn->prepare("SELECT farmer_id FROM farmers WHERE rsbsa_id = ?");
                     $stmt_rsbsa_check->bind_param("s", $rsbsa_id);
                     $stmt_rsbsa_check->execute();
                     $stmt_rsbsa_check->store_result();
                     if ($stmt_rsbsa_check->num_rows > 0) {
                         $message = "The RSBSA ID is already registered for another farmer.";
                         $message_type = 'danger';
                         $valid = false; // Stop further processing
                     }
                     $stmt_rsbsa_check->close();
                }

                if ($valid) {
                    // Hash the password using SHA256 
                    $password_hash = hash('sha256', $password);

                    // --- Start Transaction ---
                    $conn->begin_transaction();
                    $success = true;

                    // --- Generate unique code ---
                    $characters = '123456789abcdefghijklmnopqrstuvwxyz';
                    $charactersLength = strlen($characters);
                    $code_length = 8;

                    do {
                        $generated_code = '';
                        for ($i = 0; $i < $code_length; $i++) {
                            // random_int is cryptographically secure
                            $generated_code .= $characters[random_int(0, $charactersLength - 1)]; 
                        }
                        $stmt_code_check = $conn->prepare("SELECT user_id FROM users WHERE generated_code = ?");
                        $stmt_code_check->bind_param("s", $generated_code);
                        $stmt_code_check->execute();
                        $stmt_code_check->store_result();
                        $code_exists = $stmt_code_check->num_rows > 0;
                        $stmt_code_check->close();
                    } while ($code_exists);
                    // --- End unique code generation ---

                    // 1. Insert new user into the users table (Required for ALL user types)
                    $stmt_insert = $conn->prepare("INSERT INTO users (username, password_hash, name, user_type, generated_code) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("sssss", $username, $password_hash, $name, $user_type_new, $generated_code);
                        if (!$stmt_insert->execute()) {
                            $success = false;
                            $message = "Error adding user account: " . $stmt_insert->error;
                            $message_type = 'danger';
                            error_log("User Insert Error: " . $stmt_insert->error);
                        }
                        $stmt_insert->close();
                    } else {
                        $success = false;
                        $message = "Database error: Could not prepare statement to add user.";
                        $message_type = 'danger';
                        error_log("Failed to prepare statement for adding user: " . $conn->error);
                    }

                    // 2. If user is a farmer, insert into the 'farmers' table (Combined Logic)
                    if ($success && $user_type_new === 'farmer') {
                        $new_user_id = $conn->insert_id; // Get the ID of the newly inserted user

                        // Prepare Land Details JSON (matching the secondary script's logic)
                        $land_details_array = [
                            'location' => $land_location,
                            'size' => $land_size
                        ];
                        $land_details_json = json_encode($land_details_array);
                        $crop_name = $main_crop; // Matches the 'crop' column in the 'farmers' table

                        // ASSUMPTION: 'farmers' table exists with the schema below
                        $stmt_farmer = $conn->prepare("
                            INSERT INTO farmers 
                            (user_id, rsbsa_id, first_name, middle_name, last_name, address, contact_number, land_details, age, gender, civil_status, crop)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        if ($stmt_farmer) {
                            // Parameter types: i, s, s, s, s, s, s, s, i, s, s, s
                            $stmt_farmer->bind_param(
                                "isssssssisss", 
                                $new_user_id, $rsbsa_id, $first_name, $middle_name, $last_name, 
                                $address, $contact_number, $land_details_json, $age, $gender, 
                                $civil_status, $crop_name
                            );
                            
                            if (!$stmt_farmer->execute()) {
                                $success = false;
                                $message = "Error adding farmer details: " . $stmt_farmer->error;
                                $message_type = 'danger';
                                error_log("Farmer Detail Insert Error: " . $stmt_farmer->error);
                            }
                            $stmt_farmer->close();
                        } else {
                            $success = false;
                            $message = "Database error: Could not prepare statement to add farmer details.";
                            $message_type = 'danger';
                            error_log("Failed to prepare statement for adding farmer details: " . $conn->error);
                        }
                    }

                    // --- Commit or Rollback Transaction ---
                    if ($success) {
                        $conn->commit();
                        // --- PRG Redirect on Success ---
                        $success_message = "User '" . htmlspecialchars($name) . "' added successfully as '" . htmlspecialchars($user_type_new) . "'. Code: " . $generated_code;
                        if ($user_type_new === 'farmer') {
                             $success_message .= " (RSBSA ID: " . htmlspecialchars($rsbsa_id) . "). Farmer details were registered simultaneously.";
                        }
                        $_SESSION['message'] = $success_message;
                        $_SESSION['message_type'] = 'success';
                        
                        // Close connection and redirect
                        $conn->close();
                        header("location: admin-add_user.php"); 
                        exit();
                    } else {
                        $conn->rollback();
                        // Message already set inside the error blocks
                    }
                }
            }
            $stmt_check->close();
        }
    }
}

if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
<!-- HTML and JavaScript remains the same for the form, toggle, and styling -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin - Add User</title>
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

<!-- Google Fonts (Matching Dashboard) -->
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

<!-- Custom Styles (Copied and adapted from farmer-dashboard.php) -->
<style>
    body {
        /* MODIFIED: Changed font-family to Poppins for body/content text */
        font-family: "Poppins", sans-serif;
        background: #f8f9fa;
        font-size: 16px;
        line-height: 1.6;
        color: #212529;
        margin: 0;
    }

    /* --- Sidebar Styles --- */
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
        display: flex;
        flex-direction: column;
        transition: left 0.3s ease;
        font-family: "Be Vietnam Pro", sans-serif;
    }

    .sidebar-menu-label {
        color: rgba(255, 255, 255, 0.7);
        padding: 0 1rem 0.5rem 1rem;
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .sidebar.collapsed {
        left: -250px;
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

    /* Header Brand (Logo and Text) */
    .sidebar .header-brand {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: flex-start;
        text-decoration: none;
        margin-bottom: 2rem;
        padding: 0 1rem;
    }

    .sidebar .header-brand img {
        width: auto;
        max-width: 40px;
        height: auto;
        background: #19860f;
        padding: 2px;
        border-radius: 4px;
    }

    .sidebar .header-brand div {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin-top: 0;
        margin-left: 8px;
    }

    .sidebar .nav {
        flex: 1;
        margin: 0;
        padding: 0;
    }

    .sidebar .sidebar-logout {
        margin-top: auto;
        padding-top: 0.3rem;
        padding-bottom: 0;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    /* Fixed Top Header */
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
        transition: left 0.3s ease;
        font-family: "Be Vietnam Pro", sans-serif;
    }

    .card-header-custom.collapsed {
        left: 0;
    }

    #sidebarToggleBtn {
        color: #0f5132;
    }

    #sidebarToggleBtn:hover {
        color: #146c0b;
    }

    /* --- Main Content Area --- */
    main {
        margin-left: 250px;
        padding: 72px 2rem 2rem 2rem;
        background: #f8f9fa;
        min-height: 100vh;
        transition: margin-left 0.3s ease;
    }

    main.collapsed {
        margin-left: 0;
    }

    /* Typography Consistency: Titles */
    h1, h2, h3, h4, h5, h6, .card-title, .page-title {
        font-family: "Be Vietnam Pro", sans-serif;
        color: #0f5132; /* Dark Green */
    }

    .page-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    
    .dashboard-description {
        font-size: 0.875rem; /* 14px */
    }

    .card-title {
        font-size: 1.25rem;
        font-weight: 600;
    }

    /* Explicit Standard Card Text Size */
    .card-text, .card-body p:not(.card-title) {
        font-size: 0.9375rem; /* ~15px */
    }


    /* Button Theme */
    .btn-theme {
        background-color: #19860f;
        color: #fff;
        border-color: #19860f;
        font-family: "Be Vietnam Pro", sans-serif;
    }

    .btn-theme:hover {
        background-color: #146c0b;
        border-color: #146c0b;
        color: #fff;
    }

    .card {
        border-radius: 0.5rem;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        margin-bottom: 1rem;
        border: 1px solid #ddd;
    }

    .required-star {
        color: #dc3545; /* Bootstrap danger color */
        font-weight: bold;
    }
</style>
</head>
<body>
<!-- Sidebar (Consistent Design) -->
<nav class="sidebar">
    <!-- Logo and Text -->
    <!-- Adjusted logo path/text as per the second script's style, but keeping the link generic -->
    <a href="admin-dashboard.php" class="header-brand"> 
        <img src="../photos/logo.png" alt="Department of Agriculture Logo" />
        <div>Agriconnect</div>
    </a>

    <!-- Menu Label (Admin Context) -->
    <div class="sidebar-menu-label">Main Menu</div>

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
    </ul>
    
    <!-- Logout Section -->
    <div class="sidebar-logout">
        <a href="admin-logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>

<!-- Header (Consistent Design - No Notification Bell) -->
<div class="card-header card-header-custom d-flex justify-content-between align-items-center">
    <!-- Sidebar Toggle Button -->
    <button id="sidebarToggleBtn" class="btn btn-link p-0 text-dark" title="Toggle Sidebar" style="font-size: 1.5rem;">
        <i class="fas fa-bars"></i>
    </button>
    <!-- Greeting -->
    <div class="d-flex align-items-center">
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
    </div>
</div>

<!-- Main Content -->
<main>
    <div class="container">
        <!-- Consistent Page Title and Subtitle -->
        <h1 class="page-title">Add New User & Register Details</h1>
        <p class="text-muted mb-4 dashboard-description">
            Create accounts for Farmers, MAOs, or Admins. Farmer details are registered immediately upon creation.
        </p>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">User Account Details (Required for All Users)</h5>
                <form action="admin-add_user.php" method="POST">
                    <div class="mb-3">
                        <label for="name" class="form-label card-text">Full Name <span class="required-star">*</span></label>
                        <input type="text" class="form-control card-text" id="name" name="name" required 
                            value="<?php echo htmlspecialchars($post_values['name']); ?>" />
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label card-text">Username (Email format recommended) <span class="required-star">*</span></label>
                        <input type="text" class="form-control card-text" id="username" name="username"
                            placeholder="name@example.com" required 
                            value="<?php echo htmlspecialchars($post_values['username']); ?>" />
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label card-text">Password <span class="required-star">*</span></label>
                        <!-- For security, only pre-fill password on error if required to simplify the form, though often cleared. Here, we keep it standard empty -->
                        <input type="password" class="form-control card-text" id="password" name="password" required /> 
                    </div>
                    <div class="mb-4">
                        <label for="user_type" class="form-label card-text">User Type <span class="required-star">*</span></label>
                        <select class="form-select card-text" id="user_type" name="user_type" required>
                            <option value="">Select User Type</option>
                            <option value="farmer" <?php echo ($post_values['user_type'] === 'farmer' ? 'selected' : ''); ?>>Farmer</option>
                            <option value="mao" <?php echo ($post_values['user_type'] === 'mao' ? 'selected' : ''); ?>>MAO</option>
                            <option value="admin" <?php echo ($post_values['user_type'] === 'admin' ? 'selected' : ''); ?>>Admin</option>
                        </select>
                    </div>
                    
                    <!-- SECTION: Farmer Specific Details (Toggled) -->
                    <div id="farmer-details-form" style="display: none;">
                        <hr>
                        <h5 class="card-title text-success">Farmer Details (Required for Farmers)</h5>
                        
                        <div class="mb-3">
                            <label for="rsbsa_id" class="form-label card-text">RSBSA ID <span class="required-star">*</span></label>
                            <input type="text" class="form-control card-text" id="rsbsa_id" name="rsbsa_id" 
                                value="<?php echo htmlspecialchars($post_values['rsbsa_id']); ?>" />
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="first_name" class="form-label card-text">First Name <span class="required-star">*</span></label>
                                <input type="text" class="form-control card-text" id="first_name" name="first_name" 
                                    value="<?php echo htmlspecialchars($post_values['first_name']); ?>" />
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="middle_name" class="form-label card-text">Middle Name</label>
                                <input type="text" class="form-control card-text" id="middle_name" name="middle_name" 
                                    value="<?php echo htmlspecialchars($post_values['middle_name']); ?>" />
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="last_name" class="form-label card-text">Last Name <span class="required-star">*</span></label>
                                <input type="text" class="form-control card-text" id="last_name" name="last_name" 
                                    value="<?php echo htmlspecialchars($post_values['last_name']); ?>" />
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label card-text">Address <span class="required-star">*</span></label>
                            <input type="text" class="form-control card-text" id="address" name="address" 
                                value="<?php echo htmlspecialchars($post_values['address']); ?>" />
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="contact_number" class="form-label card-text">Contact Number <span class="required-star">*</span></label>
                                <input type="text" class="form-control card-text" id="contact_number" name="contact_number" 
                                    value="<?php echo htmlspecialchars($post_values['contact_number']); ?>" />
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="age" class="form-label card-text">Age <span class="required-star">*</span></label>
                                <input type="number" class="form-control card-text" id="age" name="age" min="18" 
                                    value="<?php echo htmlspecialchars($post_values['age']); ?>" />
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="gender" class="form-label card-text">Gender <span class="required-star">*</span></label>
                                <select class="form-select card-text" id="gender" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo ($post_values['gender'] === 'Male' ? 'selected' : ''); ?>>Male</option>
                                    <option value="Female" <?php echo ($post_values['gender'] === 'Female' ? 'selected' : ''); ?>>Female</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="civil_status" class="form-label card-text">Civil Status <span class="required-star">*</span></label>
                                <select class="form-select card-text" id="civil_status" name="civil_status">
                                    <option value="">Select Civil Status</option>
                                    <option value="Single" <?php echo ($post_values['civil_status'] === 'Single' ? 'selected' : ''); ?>>Single</option>
                                    <option value="Married" <?php echo ($post_values['civil_status'] === 'Married' ? 'selected' : ''); ?>>Married</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="land_location" class="form-label card-text">Land Location <span class="required-star">*</span></label>
                                <input type="text" class="form-control card-text" id="land_location" name="land_location" 
                                    value="<?php echo htmlspecialchars($post_values['land_location']); ?>" />
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="land_size" class="form-label card-text">Land Size (e.g., "1.5 hectares") <span class="required-star">*</span></label>
                                <input type="text" class="form-control card-text" id="land_size" name="land_size" 
                                    value="<?php echo htmlspecialchars($post_values['land_size']); ?>" />
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="main_crop" class="form-label card-text">Main Crop <span class="required-star">*</span></label>
                            <input type="text" class="form-control card-text" id="main_crop" name="main_crop" 
                                value="<?php echo htmlspecialchars($post_values['main_crop']); ?>" />
                        </div>
                    </div>
                    <!-- END SECTION -->
                    
                    <button type="submit" class="btn btn-theme">Add User & Register Details</button>
                </form>
            </div>
        </div>
    </div>
</main>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

<!-- JavaScript for Sidebar Toggle (Consistent Design) -->
<script>
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('main');
    const header = document.querySelector('.card-header-custom');
    const toggleBtn = document.getElementById('sidebarToggleBtn');

    function collapseSidebar() {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('collapsed');
        header.classList.add('collapsed');
        localStorage.setItem('sidebarCollapsed', 'true'); // Save state
    }

    function openSidebar() {
        sidebar.classList.remove('collapsed');
        mainContent.classList.remove('collapsed');
        header.classList.remove('collapsed');
        localStorage.setItem('sidebarCollapsed', 'false'); // Save state
    }

    // Apply saved state on page load
    const isCollapsed = localStorage.getItem('sidebarCollapsed');
    if (isCollapsed === 'true') {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('collapsed');
        header.classList.add('collapsed');
    }

    // Toggle button functionality
    toggleBtn.addEventListener('click', function() {
        if (sidebar.classList.contains('collapsed')) {
            openSidebar();
        } else {
            collapseSidebar();
        }
    });
    
    // --- NEW: JavaScript for Farmer Details Form Toggle ---
    const userTypeSelect = document.getElementById('user_type');
    const farmerDetailsDiv = document.getElementById('farmer-details-form');
    // Select all inputs/selects inside the farmer details div
    const farmerInputs = farmerDetailsDiv.querySelectorAll('input, select');
    
    function toggleFarmerDetails() {
        if (userTypeSelect.value === 'farmer') {
            farmerDetailsDiv.style.display = 'block';
            // Set required attribute dynamically for client-side validation
            farmerInputs.forEach(input => {
                // Only set required if it's not the optional middle_name field
                if (input.name !== 'middle_name') {
                    input.setAttribute('required', 'required');
                }
            });
        } else {
            farmerDetailsDiv.style.display = 'none';
            // Remove required attribute when hidden
            farmerInputs.forEach(input => {
                input.removeAttribute('required');
            });
        }
    }
    
    // Attach listener
    userTypeSelect.addEventListener('change', toggleFarmerDetails);
    
    // Initial call on load (important for repopulating form on error)
    document.addEventListener('DOMContentLoaded', function() {
        // Must call after DOM is fully loaded, especially for pre-filled forms on error
        toggleFarmerDetails(); 
    });
</script>
</body>
</html>