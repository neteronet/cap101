<?php
session_start();
include '../includes/connection.php';

// --- IMPROVEMENT 1: Robust Connection Check ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? "Connection object not set"));
    // Redirect to login on critical error
    header("location: farmers-login.php");
    exit();
}

// --- Check if the user is logged in ---
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: farmers-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Farmer'; // Default fallback
$farmer_id_display = "FRM-" . str_pad($user_id, 9, '0', STR_PAD_LEFT);
$is_farmer = false; // Flag for explicit farmer check

// --- IMPROVEMENT 2 & 3: Fetch Name AND User Type for Security Check ---
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

    // --- Explicit Farmer Authorization Check ---
    if ($db_user_type === 'farmer') {
        $is_farmer = true;
    } else {
        // If not a farmer, destroy session and redirect
        session_destroy();
        header("location: farmers-login.php");
        exit();
    }
} else {
    error_log("Failed to prepare statement for user name/type: " . $conn->error);
    // Treat preparation failure as a security risk/critical error
    session_destroy();
    header("location: farmers-login.php");
    exit();
}

// --- Application Status Variables & PRG Message Handling ---
$message = '';
$message_type = ''; 
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Initialize form field variables to null
$assistanceType = null;
$seedType = null;
$seedQuantity = null;
$engineType = null;
$remarks = null;

// --- CORE LOGIC: Check Latest Application Status for Current Year ---
$latest_app_id = null;
$latest_status = null;
$latest_qr_code = null;
$latest_approval_date = null;
$latest_claimed_status = null;

$stmt_latest = $conn->prepare("
    SELECT application_id, status, qr_code_data, DATE(approval_date), claimed
    FROM assistance_applications
    WHERE user_id = ?
      AND YEAR(application_date) = YEAR(CURDATE())
    ORDER BY application_date DESC, application_id DESC
    LIMIT 1
");

$application_exists_this_year = false;
$allow_new_application = true; // Assume true unless blocked by PENDING or APPROVED/UNCLAIMED
$is_approved_unclaimed = false; // Flag to trigger QR code display
$is_approved_and_claimed = false; // NEW FLAG: For successfully claimed subsidy

if ($stmt_latest) {
    $stmt_latest->bind_param("i", $user_id);
    $stmt_latest->execute();
    $stmt_latest->bind_result($latest_app_id, $latest_status, $latest_qr_code, $latest_approval_date, $latest_claimed_status);

    if ($stmt_latest->fetch()) {
        $application_exists_this_year = true;
        
        // Determine if application is blocked (Pending or Approved/Unclaimed)
        if ($latest_status === 'Pending') {
            $allow_new_application = false; // Blocked by Pending
        } elseif ($latest_status === 'Approved' && $latest_claimed_status == 0) {
            $allow_new_application = false; // Blocked by Approved/Unclaimed
            $is_approved_unclaimed = true;
        }
        // ADDED CHECK: If latest is Approved AND Claimed
        elseif ($latest_status === 'Approved' && $latest_claimed_status == 1) {
            $is_approved_and_claimed = true;
        }
        // If status is Rejected or Claimed (1), $allow_new_application remains true
    }
    $stmt_latest->close();
}

// --- QR Code Generation/Update Logic (Runs only if $is_approved_unclaimed is true) ---
$approved_qr_code = null;
if ($is_approved_unclaimed) {
    if (empty($latest_qr_code)) {
        // Generate QR data string
        $generated_qr_data = "app_id:" . $latest_app_id . "&user_id:" . $user_id . "&approved_on:" . $latest_approval_date;
        $approved_qr_code = $generated_qr_data;

        // Update database to save the generated code
        $update_stmt = $conn->prepare("UPDATE assistance_applications SET qr_code_data = ? WHERE application_id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("si", $generated_qr_data, $latest_app_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    } else {
        $approved_qr_code = $latest_qr_code;
    }
}


// --- Form Submission Handler (Only process POST if a new application is allowed) ---
if ($allow_new_application && $_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate common fields
    $assistanceType = filter_input(INPUT_POST, 'assistanceType', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $remarks = filter_input(INPUT_POST, 'remarks', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Validate main assistance type
    if (empty($assistanceType)) {
        $message = "Please select a Type of Assistance.";
        $message_type = "danger";
    } else {
        // Handle specific assistance types and their required fields
        switch ($assistanceType) {
            case 'Seeds':
                $seedType = filter_input(INPUT_POST, 'seedType', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $seedQuantity = filter_input(INPUT_POST, 'seedQuantity', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                
                if (empty($seedType) || empty($seedQuantity)) {
                    $message = "Please select Seed Type and Seed Quantity.";
                    $message_type = "danger";
                }
                break;
            case 'Fuel':
                $engineType = filter_input(INPUT_POST, 'engineType', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                
                if (empty($engineType)) {
                    $message = "Please select an Engine Type for fuel assistance.";
                    $message_type = "danger";
                }
                break;
            // Fertilizer and Cash Assistance require no other fields
            case 'Fertilizer':
            case 'Cash Assistance':
                break;
            default:
                $message = "Invalid assistance type selected.";
                $message_type = "danger";
                break;
        }

        // If no validation errors so far, proceed with database insertion
        if (empty($message)) {
            $status = 'Pending'; // Default status for new applications

            $insert_stmt = $conn->prepare("INSERT INTO assistance_applications (user_id, assistance_type, seed_type, seed_quantity, engine_type, remarks, status, application_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

            if ($insert_stmt === false) {
                error_log("Failed to prepare statement for insert: " . $conn->error);
                $message = "Database error: Could not prepare request. Please try again.";
                $message_type = "danger";
            } else {
                // Prepare null/empty string variables for non-selected options
                $db_seedType = ($assistanceType == 'Seeds') ? $seedType : '';
                $db_seedQuantity = ($assistanceType == 'Seeds') ? $seedQuantity : '';
                $db_engineType = ($assistanceType == 'Fuel') ? $engineType : '';
                
                // Bind parameters
                $insert_stmt->bind_param("issssss", 
                    $user_id, 
                    $assistanceType, 
                    $db_seedType,        
                    $db_seedQuantity,    
                    $db_engineType,      
                    $remarks, 
                    $status
                );

                // Execute the statement
                if ($insert_stmt->execute()) {
                    // --- Post/Redirect/Get (PRG) Pattern for Success ---
                    $_SESSION['message'] = "Your assistance request has been submitted successfully! Status: Pending.";
                    $_SESSION['message_type'] = "success";
                    $insert_stmt->close();
                    $conn->close(); // Close connection before redirect
                    header("Location: " . $_SERVER['PHP_SELF']); 
                    exit();
                } else {
                    error_log("Error submitting request for user $user_id: " . $insert_stmt->error);
                    $message = "Error submitting your request. Please try again: " . $insert_stmt->error;
                    $message_type = "danger";
                }
                $insert_stmt->close();
            }
        }
    }
}

// Close the database connection if it's still open
if ($conn && $conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Account - Assistance Status / Application</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Google Fonts (UPDATED FOR CONSISTENCY) -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    
    <!-- Notification Bell Component (FROM DASHBOARD CODE) -->
    <?php include '../includes/notification_bell.php'; ?>
    
    <style>
        /* --- START OF CONSISTENT DESIGN STYLES (COPIED FROM DASHBOARD) --- */
        body {
            /* MODIFIED: Changed font-family to Poppins for body/content text */
            font-family: "Poppins", sans-serif;
            background: #f8f9fa;
            font-size: 16px;
            line-height: 1.6;
            color: #212529;
            margin: 0;
        }

        /* --- Sidebar Styles (FROM DASHBOARD) --- */
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
            /* MODIFIED: Explicitly set sidebar font to Be Vietnam Pro for UI/Nav consistency */
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .sidebar-menu-label {
            color: rgba(255, 255, 255, 0.7);
            /* Slightly transparent white */
            padding: 0 1rem 0.5rem 1rem;
            /* Padding to align with links */
            font-size: 0.75rem;
            /* Small text */
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            /* Inherits Be Vietnam Pro from .sidebar */
        }

        .sidebar.collapsed {
            left: -250px;
        }

        .sidebar .nav {
            flex: 1;
            margin: 0;
            padding: 0;
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
            transition: all 0.2s ease-in-out;
            /* Inherits Be Vietnam Pro from .sidebar */
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
        
        /* MODIFIED: Header Brand (Logo and Text) - FROM DASHBOARD */
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
        /* END MODIFIED */

        .sidebar .sidebar-logout {
            margin-top: auto;
            padding-top: 0.3rem;
            padding-bottom: 0;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* --- Fixed Top Header (FROM DASHBOARD) --- */
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
            /* MODIFIED: Explicitly set header font to Be Vietnam Pro for UI consistency */
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

        /* --- Main Content Area (FROM DASHBOARD) --- */
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
        
        /* Hide the old button structure */
        .logout-btn {
            display: none; 
        }

        /* 1. Color Palette Standardization: Links */
        a {
            color: #19860f;
        }

        a:hover {
            color: #146c0b;
        }
        
        /* --- Theme Buttons/Colors (FROM DASHBOARD) --- */
        .btn-theme {
            background-color: #19860f;
            color: #fff;
            font-size: 15px;
            padding: 10px 20px;
            border-radius: 4px;
            transition: all 0.2s ease;
            border: 1px solid #19860f;
            font-family: "Be Vietnam Pro", sans-serif; /* Consistent UI font */
        }

        .btn-theme:hover {
            background-color: #146c0b;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #146c0b;
        }

        /* 2. Button and Alert Unification: Outline Button Theme */
        .btn-outline-theme {
            color: #19860f;
            border-color: #19860f;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .btn-outline-theme:hover,
        .btn-outline-theme:active {
            background-color: #146c0b;
            color: #fff;
            border-color: #146c0b;
        }

        .btn-outline-theme:focus {
            box-shadow: 0 0 0 0.25rem rgba(25, 134, 15, 0.5);
        }

        /* --- Typography Consistency: Headings and Titles (FROM DASHBOARD) --- */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .card-title,
        .page-title {
            font-family: "Be Vietnam Pro", sans-serif;
            color: #0f5132;
        }
        
        /* MODIFIED: Page Title size/color to match Dashboard */
        .page-title {
            font-size: 1.5rem; 
            font-weight: 600;
            color: #0f5132; 
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        /* NEW: Standard Card Title - Explicitly set size to match Dashboard's 1.25rem */
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f5132;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
        }

        /* NEW: Status Card H2 - To ensure consistent typography for status messages */
        .card h2 {
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 1.5rem; /* Consistent with page-title size */
            font-weight: 600;
        }

        .card-title i {
            margin-right: 8px;
        }

        /* NEW: Explicit Standard Content Text Size (0.9375rem = 15px) for consistency */
        .card-body p:not(.card-title), 
        .form-label, 
        .form-text,
        .card-text {
            /* Inherits Poppins from body */
            font-size: 0.9375rem; /* ~15px */
        }

        /* NEW: Form element size consistency */
        .form-select, .form-control {
            font-size: 0.9375rem;
        }
        
        /* --- Utility & Component Styles (FROM DASHBOARD) --- */
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            border: 1px solid #ddd;
        }

        /* Custom status badge classes */
        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.4rem;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        /* Re-mapping status badges to Bootstrap colors for theme consistency */
        .status-pending {
            background-color: #ffc107 !important; /* Warning */
            color: #664d03 !important;
        }

        .status-approved {
            background-color: #198754 !important; /* Success */
            color: #fff !important;
        }

        .status-rejected {
            background-color: #dc3545 !important; /* Danger */
            color: #fff !important;
        }

        /* --- Custom Styles unique to this page (RETAINED) --- */
        .qr-code {
            text-align: center;
            margin-top: 1rem;
        }

        .qr-code img {
            width: 180px;
            height: 180px;
            border: 5px solid #19860f;
            border-radius: 8px;
            padding: 8px;
            background: #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        /* Custom alert style for info box (Updated font-size) */
        .alert-info-custom {
            background-color: #e6f2e6;
            color: #157a0d;
            border-color: #aed5ae;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            font-size: 0.9375rem;
            font-family: "Poppins", sans-serif; /* Use content font for alert message */
        }

        .alert-info-custom i {
            margin-right: 15px;
            font-size: 1.4rem;
            color: #19860f;
        }

        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            /* Inherits Poppins/font-size from new addition */
        }

        .form-label i {
            margin-right: 8px;
            color: #19860f;
        }
        /* --- END Custom Styles unique to this page --- */

        /* ----------------------------------------------------------- */
        /* --- Notification Bell Styling for Consistency (FROM DASHBOARD) --- */
        /* ----------------------------------------------------------- */
        .notification-bell-container {
            position: relative;
            display: inline-block;
        }

        .notification-bell {
            color: #0f5132;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.25rem; 
            padding: 0;
            line-height: 1;
            transition: color 0.2s ease;
        }

        .notification-bell:hover {
            color: #146c0b; 
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -10px;
            padding: 0.15em 0.45em;
            border-radius: 50%;
            background-color: #dc3545; 
            color: white;
            font-size: 0.6rem;
            line-height: 1;
            min-width: 18px;
            text-align: center;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .notification-badge.hidden {
            display: none;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 320px;
            background-color: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-radius: 0.5rem;
            margin-top: 8px;
            z-index: 1070;
            display: none;
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 0.875rem; 
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #eee;
            /* Changed background-color from Dashboard to inherit for a cleaner look */
        }

        .notification-header h6 {
            margin: 0;
            font-size: 1rem;
            color: #fff; 
            font-weight: 600;
        }

        .mark-all-read {
            color: #19860f; 
            background: none;
            border: none;
            font-size: 0.75rem;
            cursor: pointer;
            padding: 0;
            text-decoration: underline;
        }

        .mark-all-read:hover {
            color: #146c0b; 
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
            padding: 0; 
        }

        .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f8f9fa; 
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .notification-item:hover {
            background-color: #f1f1f1;
        }

        .notification-item.unread {
            background-color: #f7fff6; 
            border-left: 3px solid #19860f; 
            padding-left: calc(1rem - 3px); 
            font-weight: 500;
        }

        .notification-item.unread p {
            color: #0f5132; 
        }

        .notification-item p {
            margin: 0;
            line-height: 1.4;
        }

        .notification-item strong {
            font-weight: 600;
        }

        .notification-item:last-child {
            border-bottom: none;
        }
        /* --- END Notification Bell Styling --- */
    </style>


</head>

<body>
    <!-- Sidebar (FROM DASHBOARD) -->
    <nav class="sidebar">
        <!-- New Header Brand (Logo and Text) -->
        <a class="header-brand">
            <img src="../photos/logo.png" alt="Province of Antique" />
            <div>Agriconnect</div>
        </a>

        <div class="sidebar-menu-label">Main Menu</div>

        <ul class="nav flex-column">
            <li class="nav-item"><a href="farmer-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <!-- Active link is here -->
            <li class="nav-item"><a href="farmer-apply_for_assistance.php" class="nav-link active"><i class="fas fa-hand-holding-usd"></i>Apply for Assistance</a></li>
            <li class="nav-item"><a href="farmer-planting_status.php" class="nav-link"><i class="fas fa-leaf"></i> Planting Status</a></li>
            <li class="nav-item"><a href="farmer-claim_history.php" class="nav-link"><i class="fas fa-history"></i> Claim History</a></li>
            <li class="nav-item"><a href="farmer-announcement.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li class="nav-item"><a href="farmer-my_profile.php" class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a></li>
        </ul>
        
        <!-- Logout Link (Moved to bottom of sidebar) -->
        <div class="sidebar-logout">
            <a href="farmers-logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Header (FROM DASHBOARD) -->
    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
        <!-- New Toggle Button -->
        <button id="sidebarToggleBtn" class="btn btn-link p-0 text-dark" title="Toggle Sidebar" style="font-size: 1.5rem;">
            <i class="fas fa-bars"></i>
        </button>
        <!-- Right side alignment wrapper -->
        <div class="d-flex align-items-center">
            <!-- Greeting (Consistent) -->
            <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>

            <!-- Notification Bell (FROM DASHBOARD CODE) -->
            <div class="notification-bell-container me-3">
                <button class="notification-bell" id="notificationBell" onclick="toggleNotificationDropdown()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge hidden" id="notificationBadge">0</span>
                </button>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h6><i class="fas fa-bell me-2"></i>Notifications</h6>
                        <button class="mark-all-read" onclick="markAllAsRead()">Mark all as read</button>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="notification-loading">Loading notifications...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <main>
        <div class="container">

            <!-- Added Page Title for Consistency -->
            <h1 class="page-title">
                <i></i>Assistance Application & Status
            </h1>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php if ($message_type == 'success'): ?>
                        <i class="fas fa-check-circle me-2"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php endif; ?>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php 
            // --- CONDITIONAL CONTENT RENDERING ---
            if ($is_approved_unclaimed): 
                // TEMPLATE 1: APPROVED AND UNCLAIMED (Show QR Code)
            ?>
                <div class="card shadow p-4 text-center border-success border-3">
                    <h2 class="text-success mb-3"><i class="fas fa-check-circle"></i> Assistance Approved!</h2>
                    <p class="text-muted mb-4">
                        Your latest approved assistance for **<?php echo date('Y'); ?>** is ready for claiming.
                        Please use your QR code below to claim it at the designated office.
                    </p>
                    <div class="qr-code mb-3">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php echo urlencode($approved_qr_code); ?>&size=220x220" alt="QR Code">
                    </div>
                    <p><strong>Application ID:</strong> <?php echo htmlspecialchars($latest_app_id); ?></p>
                    <p><strong>Farmer ID:</strong> <?php echo htmlspecialchars($farmer_id_display); ?></p>
                    <p><strong>Approval Date:</strong> <?php echo htmlspecialchars($latest_approval_date); ?></p>
                    <p class="text-danger small mt-2">
                        **DO NOT SHARE THIS CODE PUBLICLY.** It is linked to your assistance claim.
                    </p>
                    <!-- THE DOWNLOAD BUTTON - Functionality is already correct and present. -->
                    <button class="btn btn-theme col-lg-4 col-md-6 mx-auto mt-3" onclick="downloadQRCode('<?php echo urlencode($approved_qr_code); ?>', '<?php echo htmlspecialchars($farmer_id_display); ?>')" style="background-color: #198754; border-color: #198754;">
                        <i class="fas fa-download me-2"></i> Download QR Code
                    </button>
                </div>

            <?php elseif ($application_exists_this_year && $latest_status === 'Pending'): 
                // TEMPLATE 2: PENDING (Show Waiting Message)
            ?>
                <div class="card shadow p-4 text-center border-warning border-3">
                    <h2 class="text-warning mb-3"><i class="fas fa-hourglass-half"></i> Application Pending Review</h2>
                    <p class="text-muted mb-4">
                        Your latest assistance application (ID: **<?php echo htmlspecialchars($latest_app_id); ?>**) for **<?php echo date('Y'); ?>** is currently **Pending** approval from the Provincial Agriculture Office.
                    </p>
                    <p class="card-text text-muted">
                        Please check back later for an update on your status. You cannot submit a new request while one is pending.
                    </p>
                    <i class="fas fa-clock fa-4x text-warning my-4"></i>
                </div>

            <?php else: 
                // TEMPLATE 3: ALLOWED TO APPLY (Show Form) 
                // (Covers: No application this year, Latest is Rejected, Latest is Claimed)
            ?>

                <?php if ($is_approved_and_claimed): 
                    // NEW TEMPLATE FOR CLAIMED SUBSIDY
                ?>
                    <div class="card shadow p-4 text-center border-success border-3 mb-4">
                        <h2 class="text-success mb-3"><i class="fas fa-award"></i> Assistance Successfully Claimed!</h2>
                        <p class="text-muted mb-4">
                            Your previous assistance application (ID: **<?php echo htmlspecialchars($latest_app_id); ?>**) for **<?php echo date('Y'); ?>** was successfully **Claimed**.
                        </p>
                        <i class="fas fa-seedling fa-4x text-success my-4"></i>
                        <p class="card-text text-muted">
                            You may now submit a new assistance request for the current year.
                        </p>
                    </div>
                <?php endif; ?>
                
                <div class="alert alert-info-custom" role="alert">
                    <i class="fas fa-info-circle"></i>
                    Please fill in the form below to request support. You are currently eligible to apply for new assistance for this year.
                </div>

                <?php if ($application_exists_this_year && $latest_status === 'Rejected'): ?>
                    <div class="alert alert-info border-info" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Note: Your last application (ID: **<?php echo htmlspecialchars($latest_app_id); ?>**) was **<?php echo htmlspecialchars($latest_status); ?>**. You may submit a new request.
                    </div>
                <?php endif; ?>


                <div class="card">
                    <div class="card-header">
                        Assistance Request Form
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label for="assistanceType" class="form-label">
                                    <i class="fas fa-hands-helping"></i>Type of Assistance
                                </label>
                                <select class="form-select" id="assistanceType" name="assistanceType" required>
                                    <option value="">-- Select Assistance --</option>
                                    <option value="Seeds" <?php echo ($assistanceType == 'Seeds') ? 'selected' : ''; ?>>Seeds</option>
                                    <option value="Fertilizer" <?php echo ($assistanceType == 'Fertilizer') ? 'selected' : ''; ?>>Fertilizer</option>
                                    <option value="Fuel" <?php echo ($assistanceType == 'Fuel') ? 'selected' : ''; ?>>Fuel</option>
                                    <option value="Cash Assistance" <?php echo ($assistanceType == 'Cash Assistance') ? 'selected' : ''; ?>>Cash Assistance</option>
                                </select>
                            </div>

                            <!-- Dynamic Seed Details Section -->
                            <div id="seedDetails" class="mb-4" style="display: <?php echo ($assistanceType == 'Seeds') ? 'block' : 'none'; ?>;">
                                <div class="mb-4">
                                    <label for="seedType" class="form-label">
                                        <i class="fas fa-seedling"></i>Seed Type
                                    </label>
<select class="form-select" id="seedType" name="seedType">
    <option value="">-- Select Seed Type --</option>
    <option value="Hybrid Rice" <?php echo ($seedType == 'Hybrid Rice') ? 'selected' : ''; ?>>Hybrid Rice</option>
    <option value="Inbred Rice" <?php echo ($seedType == 'Inbred Rice') ? 'selected' : ''; ?>>Inbred Rice</option>
    <option value="Hybrid Corn" <?php echo ($seedType == 'Hybrid Corn') ? 'selected' : ''; ?>>Hybrid Corn</option>
    <option value="Inbred Corn" <?php echo ($seedType == 'Inbred Corn') ? 'selected' : ''; ?>>Inbred Corn</option>
    <option value="pechay" <?php echo ($seedType == 'pechay') ? 'selected' : ''; ?>>Pechay</option>
    <option value="kangkong" <?php echo ($seedType == 'kangkong') ? 'selected' : ''; ?>>Kangkong</option>
    <option value="mustasa" <?php echo ($seedType == 'mustasa') ? 'selected' : ''; ?>>Mustasa</option>
    <option value="alugbati" <?php echo ($seedType == 'alugbati') ? 'selected' : ''; ?>>Alugbati</option>
    <option value="malunggay" <?php echo ($seedType == 'malunggay') ? 'selected' : ''; ?>>Malunggay</option>
    <option value="sitaw" <?php echo ($seedType == 'sitaw') ? 'selected' : ''; ?>>Sitaw</option>
    <option value="ampalaya" <?php echo ($seedType == 'ampalaya') ? 'selected' : ''; ?>>Ampalaya</option>
    <option value="okra" <?php echo ($seedType == 'okra') ? 'selected' : ''; ?>>Okra</option>
    <option value="talong" <?php echo ($seedType == 'talong') ? 'selected' : ''; ?>>Talong</option>
    <option value="kamatis" <?php echo ($seedType == 'kamatis') ? 'selected' : ''; ?>>Kamatis</option>
    <option value="sibuyas" <?php echo ($seedType == 'sibuyas') ? 'selected' : ''; ?>>Sibuyas</option>
    <option value="kadyos" <?php echo ($seedType == 'kadyos') ? 'selected' : ''; ?>>Kadyos</option>
    <option value="kamote" <?php echo ($seedType == 'kamote') ? 'selected' : ''; ?>>Kamote</option>
    <option value="gabi" <?php echo ($seedType == 'gabi') ? 'selected' : ''; ?>>Gabi</option>
    <option value="carrots" <?php echo ($seedType == 'carrots') ? 'selected' : ''; ?>>Carrots</option>
    <option value="redish" <?php echo ($seedType == 'redish') ? 'selected' : ''; ?>>Redish</option>
    <option value="cassava" <?php echo ($seedType == 'cassava') ? 'selected' : ''; ?>>Cassava</option>
</select>
                                </div>
                                <div class="mb-4">
                                    <label for="seedQuantity" class="form-label">
                                        <i class="fas fa-boxes"></i>Seed Quantity (e.g., in kg)
                                    </label>
                                    <select class="form-select" id="seedQuantity" name="seedQuantity">
                                        <option value="">-- Select Quantity --</option>
                                        <option value="10kg" <?php echo ($seedQuantity == '10kg') ? 'selected' : ''; ?>>10 kg</option>
                                        <option value="20kg" <?php echo ($seedQuantity == '20kg') ? 'selected' : ''; ?>>20 kg</option>
                                        <option value="25kg" <?php echo ($seedQuantity == '25kg') ? 'selected' : ''; ?>>25 kg</option>
                                        <option value="50kg" <?php echo ($seedQuantity == '50kg') ? 'selected' : ''; ?>>50 kg</option>
                                        <option value="100kg" <?php echo ($seedQuantity == '100kg') ? 'selected' : ''; ?>>100 kg</option>
                                    </select>
                                </div>
                            </div>
                            <!-- End Dynamic Seed Details Section -->

                            <!-- Dynamic Engine Details Section -->
                            <div id="engineDetails" class="mb-4" style="display: <?php echo ($assistanceType == 'Fuel') ? 'block' : 'none'; ?>;">
                                <label for="engineType" class="form-label">
                                    <i class="fas fa-tractor"></i>Engine Type
                                </label>
                                <select class="form-select" id="engineType" name="engineType">
                                    <option value="">-- Select Engine Type --</option>
                                    <option value="Tractor" <?php echo ($engineType == 'Tractor') ? 'selected' : ''; ?>>Tractor</option>
                                    <option value="Water Pump" <?php echo ($engineType == 'Water Pump') ? 'selected' : ''; ?>>Water Pump</option>
                                    <option value="Hand Tractor" <?php echo ($engineType == 'Hand Tractor') ? 'selected' : ''; ?>>Hand Tractor</option>
                                    <option value="Generator" <?php echo ($engineType == 'Generator') ? 'selected' : ''; ?>>Generator</option>
                                    <option value="Harvester" <?php echo ($engineType == 'Harvester') ? 'selected' : ''; ?>>Harvester</option>
                                    <option value="Other" <?php echo ($engineType == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <!-- End Dynamic Engine Details Section -->

                            <div class="mb-4">
                                <label for="remarks" class="form-label">
                                    <i class="fas fa-comment-dots"></i>Remarks / Additional Details
                                </label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="5" placeholder="Explain why you need this assistance, how it will be used, and any other relevant information."><?php echo htmlspecialchars($remarks ?? ''); ?></textarea>
                                <small class="form-text text-muted">Provide a clear explanation to support your request.</small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-theme">
                                    <i class="fas fa-paper-plane"></i>Submit Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php endif; ?>
            <!-- End Conditional Content -->

        </div>
    </main>

    <!-- Bootstrap JS and Custom Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function for QR Code Download (used in Template 1) - MODIFIED FOR ROBUST CROSS-ORIGIN DOWNLOAD
        function downloadQRCode(qrData, farmerId) {
            const qrCodeUrl = `https://api.qrserver.com/v1/create-qr-code/?data=${qrData}&size=400x400`;
            const filename = `Farmer_QRCode_${farmerId || 'Claim'}.png`;
            
            // Use fetch to get the image data as a Blob
            fetch(qrCodeUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.blob();
                })
                .then(blob => {
                    // Create a temporary URL for the Blob object (this URL is local and can be downloaded)
                    const url = window.URL.createObjectURL(blob);
                    
                    // Create a temporary <a> element to trigger download
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = filename; // Set the desired filename
                    document.body.appendChild(link);
                    link.click(); // Trigger the download
                    
                    // Clean up by revoking the object URL and removing the link
                    // setTimeout is sometimes used to ensure the click fully registers before revoking
                    setTimeout(() => {
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(link);
                    }, 100); 
                })
                .catch(error => {
                    console.error('Error fetching QR code for download:', error);
                    alert('Failed to download QR Code. Please check your connection or try again.');
                });
        }
        
        // --- START NOTIFICATION BELL FUNCTIONS (FROM DASHBOARD) ---
        function toggleNotificationDropdown() {
            document.getElementById('notificationDropdown').classList.toggle('show');
            // Basic logic to hide the badge on open (in a real app, this would be an API call)
            document.getElementById('notificationBadge').classList.add('hidden');
        }

        // Close the dropdown if the user clicks outside of it
        window.onclick = function(event) {
            if (!event.target.matches('.notification-bell-container') && !event.target.closest('.notification-bell-container')) {
                var dropdowns = document.getElementsByClassName("notification-dropdown");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
        
        function markAllAsRead() {
            // Placeholder for real logic (e.g., AJAX call)
            console.log("Marked all notifications as read.");
            document.getElementById('notificationList').innerHTML = '<div class="notification-item text-center text-muted small py-2">No new notifications.</div>';
        }
        // --- END NOTIFICATION BELL FUNCTIONS ---
        
        // JavaScript to toggle sidebar collapse and preserve state using localStorage (FROM DASHBOARD)
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
        if(toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                if (sidebar.classList.contains('collapsed')) {
                    openSidebar();
                } else {
                    collapseSidebar();
                }
            });
        }


        // Function for Dynamic Form Fields (used in Template 3) and DOMContentLoaded for Notifications
        document.addEventListener('DOMContentLoaded', function() {
            // --- Form-specific Dynamic Fields Logic (RETAINED) ---
            const assistanceTypeSelect = document.getElementById('assistanceType');
            const seedDetailsDiv = document.getElementById('seedDetails');
            const engineDetailsDiv = document.getElementById('engineDetails');

            if (assistanceTypeSelect) { // Check if form elements exist (only in Template 3)
                function toggleDynamicFields() {
                    // Hide all dynamic sections first
                    seedDetailsDiv.style.display = 'none';
                    engineDetailsDiv.style.display = 'none';

                    // Show relevant section based on selection
                    if (assistanceTypeSelect.value === 'Seeds') {
                        seedDetailsDiv.style.display = 'block';
                    } else if (assistanceTypeSelect.value === 'Fuel') {
                        engineDetailsDiv.style.display = 'block';
                    }
                }

                // Initial check when the page loads (useful if form repopulates on error)
                toggleDynamicFields();

                // Event listener for changes in the assistance type dropdown
                assistanceTypeSelect.addEventListener('change', toggleDynamicFields);
            }
            // --- End Form-specific Dynamic Fields Logic ---
            
            // --- START NOTIFICATION BELL DOMContentLoaded LOGIC (FROM DASHBOARD) ---
            // Simulate initial notification load (in a real app, this would be an API call)
             const list = document.getElementById('notificationList');
             const badge = document.getElementById('notificationBadge');
             
             if(list && badge) { // Check if elements exist
                list.innerHTML = `
                   <div class="notification-item unread">
                       <p class="mb-1">Your loan application has been <strong>Approved</strong>!</p>
                       <span class="text-muted small">5 minutes ago</span>
                   </div>
                   <div class="notification-item unread">
                       <p class="mb-1">New advisory on pest control for Rice crops.</p>
                       <span class="text-muted small">2 hours ago</span>
                   </div>
                   <div class="notification-item">
                       <p class="mb-1">Claim for Seed Subsidy is <strong>Ready</strong>.</p>
                       <span class="text-muted small">Yesterday</span>
                   </div>
                `;
                badge.textContent = 2; // Set count
                badge.classList.remove('hidden'); // Show badge
             }
            // --- END NOTIFICATION BELL DOMContentLoaded LOGIC ---
        });
    </script>
</body>

</html>