<?php
session_start(); // Start the session at the very beginning of the script

// --- HELPER FUNCTION: Status to CSS Class Mapping (FROM CODE A) ---
function get_status_class($status) {
    $status = strtolower($status);
    if (strpos($status, 'pending') !== false || strpos($status, 'review') !== false) {
        return 'status-pending';
    } elseif (strpos($status, 'approved') !== false || strpos('claimed', $status) !== false) {
        return 'status-approved';
    } elseif (strpos($status, 'rejected') !== false || strpos($status, 'cancelled') !== false || strpos(
            $status,
            'denied'
        ) !== false) {
        return 'status-rejected';
    } else {
        return 'status-pending'; // Default fallback
    }
}
// --- END HELPER FUNCTION ---

include '../includes/connection.php'; // Ensure your connection file is correctly included

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

// --- Fetch Latest Announcements (up to 5 for carousel - FROM CODE B) ---
$announcements = [];
$stmt_announcements = $conn->prepare("SELECT title, content, publish_date FROM announcements ORDER BY publish_date DESC LIMIT 5");
if ($stmt_announcements) {
    $stmt_announcements->execute();
    $stmt_announcements->bind_result($title, $content, $publish_date);
    while ($stmt_announcements->fetch()) {
        $announcements[] = [
            'title' => $title,
            'content' => $content,
            'publish_date' => $publish_date
        ];
    }
    $stmt_announcements->close();
} else {
    // Handle error if announcement statement preparation fails
    error_log("Failed to prepare announcement statement: " . $conn->error);
}
// --- End Fetch Latest Announcements ---

// --- Fetch Latest Crop Monitoring Status for the logged-in user (FROM CODE B/A) ---
$latest_crop_status = null;
// Using farmer_crops table and crop_name column as per Code B, mapping to crop_identifier key as per Code A's usage
$stmt_crop_status = $conn->prepare("SELECT crop_name, status, update_date FROM farmer_crops WHERE user_id = ? ORDER BY update_date DESC LIMIT 1");
if ($stmt_crop_status) {
    $stmt_crop_status->bind_param("i", $user_id); // Assuming user_id is an integer
    $stmt_crop_status->execute();
    $stmt_crop_status->bind_result($crop_name, $status, $update_date);
    if ($stmt_crop_status->fetch()) {
        $latest_crop_status = [
            'crop_identifier' => $crop_name, // Mapped to crop_identifier for card display consistency
            'status' => $status,
            'update_date' => $update_date
        ];
    }
    $stmt_crop_status->close();
} else {
    // Handle error if crop status statement preparation fails
    error_log("Failed to prepare crop status statement: " . $conn->error);
}
// --- End Fetch Latest Crop Monitoring Status ---

// --- NEW: Fetch Latest Assistance Applications Status (FROM CODE A) ---
$latest_applications = [];
// Assuming table is 'assistance_applications' with columns: assistance_type, status, application_date
$stmt_applications = $conn->prepare("SELECT assistance_type, status FROM assistance_applications WHERE user_id = ? ORDER BY application_date DESC LIMIT 3");
if ($stmt_applications) {
    $stmt_applications->bind_param("i", $user_id); // Assuming user_id is an integer
    $stmt_applications->execute();
    $result_applications = $stmt_applications->get_result();

    while ($row = $result_applications->fetch_assoc()) {
        $latest_applications[] = [
            'type' => htmlspecialchars($row['assistance_type']),
            'status' => htmlspecialchars($row['status'])
        ];
    }
    $stmt_applications->close();
} else {
    // Handle error if application statement preparation fails
    error_log("Failed to prepare application status statement: " . $conn->error);
}
// --- END NEW FETCH ---


// --- NEW: Check for Pending Assistance Applications to restrict new applications (FROM CODE A) ---
$has_pending_application = false;
// We check for *any* application that has a status of 'Pending'
$stmt_check_pending = $conn->prepare("SELECT COUNT(*) FROM assistance_applications WHERE user_id = ? AND status = 'Pending'");
if ($stmt_check_pending) {
    $stmt_check_pending->bind_param("i", $user_id);
    $stmt_check_pending->execute();
    $stmt_check_pending->bind_result($pending_count);
    $stmt_check_pending->fetch();
    $stmt_check_pending->close();

    if ($pending_count > 0) {
        $has_pending_application = true;
    }
} else {
    // Log the error. In this case, we'll default to allowing application (false) but it's a critical system error.
    error_log("Failed to prepare pending assistance check statement: " . $conn->error);
}
// --- END NEW PENDING CHECK ---


$conn->close(); // Close the connection after all database operations

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Farmer Account - Dashboard</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts (FROM CODE A) -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Notification Bell Component (FROM CODE B) -->
    <?php include '../includes/notification_bell.php'; ?>

    <!-- Custom Styles (FROM CODE A, fully integrated) -->
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

        /* --- Sidebar Styles (DO NOT TOUCH) --- */
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

        /* >>> INSERTION: Sidebar Menu Label Style <<< */
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

        /* >>> END INSERTION <<< */

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

        /* >>> MODIFIED: Header Brand (Logo and Text) <<< */
        .sidebar .header-brand {
            /* Changed from column to row */
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-start;
            /* Aligns content to the start */
            text-decoration: none;
            margin-bottom: 2rem;
            padding: 0 1rem;
            /* Added padding to align with links */
        }

        .sidebar .header-brand img {
            /* Reduced size significantly */
            width: auto;
            max-width: 40px;
            height: auto;
            background: #19860f;
            padding: 2px;
            /* Reduced padding */
            border-radius: 4px;
        }

        .sidebar .header-brand div {
            /* Adjusted font size and spacing to place it beside the logo */
            font-size: 18px;
            font-weight: 700;
            /* Increased weight for prominence */
            color: #fff;
            margin-top: 0;
            /* Removed previous vertical margin */
            margin-left: 8px;
            /* Spacing between logo and text */
            /* Inherits Be Vietnam Pro from .sidebar */
        }

        /* >>> END MODIFIED <<< */

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

        /* --- Fixed Top Header (DO NOT TOUCH) --- */
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
            /* Changed to space-between for toggle button */
            z-index: 1060;
            border-bottom: 1px solid #ddd;
            transition: left 0.3s ease;
            /* MODIFIED: Explicitly set header font to Be Vietnam Pro for UI consistency */
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .card-header-custom.collapsed {
            left: 0;
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

        /* Small text improvements */
        .text-muted {
            color: #6c757d !important;
        }

        /* 1. Color Palette Standardization: Links */
        a {
            color: #19860f;
        }

        a:hover {
            color: #146c0b;
        }

        /* 5. Typography Consistency: Card Titles */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .card-title,
        .modal-title,
        .page-title {
            /* ADDED .page-title HERE */
            /* MODIFIED: Explicitly set headings/titles font to Be Vietnam Pro */
            font-family: "Be Vietnam Pro", sans-serif;
            color: #0f5132;
            /* Dark Green */
        }

        /* NEW: Style for the Page Title (Dashboard) */
        .page-title {
            font-size: 1.5rem;
            /* Reduced from default H1 (2.5rem) to be less dominating */
            font-weight: 600;
            /* Made it slightly bold for prominence */
            margin-bottom: 0.5rem;
        }

        /* END NEW */

        /* NEW: Style for the Dashboard Description Paragraph */
        .dashboard-description {
            font-size: 0.875rem;
            /* 14px */
        }

        /* END NEW */

        /* NEW: Explicit Card Title Size for Consistency */
        .card-title {
            font-size: 1.25rem;
            /* Standard H5 size, but explicitly set */
            font-weight: 600;
            /* Added slight boldness */
        }

        /* END NEW */

        /* NEW: Explicit Standard Card Text Size for Consistency (0.9375rem = 15px) */
        .card-text,
        .card-body p:not(.card-title),
        .list-unstyled li {
            /* Inherits Poppins from body */
            font-size: 0.9375rem;
            /* ~15px for better readability in main content */
        }

        .card-text.small,
        .list-unstyled.small li,
        .card-text.text-muted.small {
            font-size: 0.875rem !important;
            /* Keep 14px for elements explicitly marked as small */
        }

        /* END NEW */

        /* 2. Button and Alert Unification: Button Theme */
        .btn-theme {
            background-color: #19860f;
            color: #fff;
            border-color: #19860f;
            /* MODIFIED: setting explicitly to Be Vietnam Pro for UI consistency */
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .btn-theme:hover {
            background-color: #146c0b;
            border-color: #146c0b;
            color: #fff;
        }

        /* Disabled Button Styling */
        .btn-theme.disabled {
            background-color: #7ab372;
            /* Lighter shade of theme color for disabled */
            border-color: #7ab372;
            pointer-events: none;
            /* Ensure no click */
            opacity: 0.65;
        }

        /* 2. Button and Alert Unification: Outline Button Theme */
        .btn-outline-theme {
            color: #19860f;
            border-color: #19860f;
            /* MODIFIED: setting explicitly to Be Vietnam Pro for UI consistency */
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

        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            /* Consistent Shadow */
            margin-bottom: 1rem;
            border: 1px solid #ddd;
        }

        /* Custom status badge classes */
        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.4rem;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
            /* MODIFIED: setting explicitly to Be Vietnam Pro for UI consistency */
            font-family: "Be Vietnam Pro", sans-serif;
        }

        /* Re-mapping status badges to Bootstrap colors for theme consistency */
        .status-pending {
            background-color: #ffc107 !important;
            /* Warning */
            color: #664d03 !important;
        }

        .status-approved {
            background-color: #198754 !important;
            /* Success */
            color: #fff !important;
        }

        .status-rejected {
            background-color: #dc3545 !important;
            /* Danger */
            color: #fff !important;
        }

        /* Bootstrap default badge overrides for consistency */
        .text-info {
            color: #0dcaf0 !important;
        }

        .text-success {
            color: #198754 !important;
        }

        .text-warning {
            color: #ffc107 !important;
        }

        .text-danger {
            color: #dc3545 !important;
        }

        /* --- ADDED CUSTOM ALERT STYLES FROM planting_status.php FOR CONSISTENCY --- */
        .alert-custom-warning {
            background-color: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
            border-radius: .375rem;
            padding: .75rem 1rem;
            display: flex;
            gap: .75rem;
            align-items: flex-start;
            /* MODIFIED: setting explicitly to Be Vietnam Pro for UI consistency */
            font-family: "Be Vietnam Pro", sans-serif;
            /* >>> ADJUSTMENT FOR CONSISTENT FONT SIZE <<< */
            font-size: 0.9375rem;
        }

        .alert-custom-warning i {
            margin-top: .2rem;
        }

        .alert-heading {
            color: inherit;
        }

        /* --- END ADDED CUSTOM ALERT STYLES --- */

        .alert-warning {
            /* If standard alert-warning is still used, this will remain in case the custom one is not used */
            color: #664d03;
            background-color: #fff3cd;
            border-color: #ffecb5;
        }

        .alert-warning strong {
            color: #664d03;
        }

        #sidebarToggleBtn {
            color: #0f5132;
        }

        #sidebarToggleBtn:hover {
            color: #146c0b;
        }

        /* Carousel control adjustments for visibility in custom card theme */
        .carousel-control-prev-icon,
        .carousel-control-next-icon {
            filter: invert(100%) grayscale(100%); /* Makes them dark on light background */
        }
        
        .carousel-control-prev, 
        .carousel-control-next {
            width: 8%; /* Reduced width */
        }

        /* ----------------------------------------------------------- */
        /* --- Notification Bell Styling for Consistency (IMPROVED) --- */
        /* ----------------------------------------------------------- */
        .notification-bell-container {
            position: relative;
            display: inline-block;
        }

        .notification-bell {
            /* Base color matching the sidebar toggle button for consistency */
            color: #0f5132;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.25rem; /* Slightly larger for prominence */
            padding: 0;
            line-height: 1;
            transition: color 0.2s ease;
        }

        .notification-bell:hover {
            color: #146c0b; /* Darker theme hover color */
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -10px;
            padding: 0.15em 0.45em;
            border-radius: 50%;
            background-color: #dc3545; /* Danger Red for unread count (consistent with status-rejected) */
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
            /* Consistent font for UI elements */
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 0.875rem; /* Small font size */
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
        }

        .notification-header h6 {
            margin: 0;
            font-size: 1rem;
            color: #fff; /* Dark Green for heading consistency */
            font-weight: 600;
        }

        .mark-all-read {
            color: #19860f; /* Theme Green for action link */
            background: none;
            border: none;
            font-size: 0.75rem;
            cursor: pointer;
            padding: 0;
            text-decoration: underline;
        }

        .mark-all-read:hover {
            color: #146c0b; /* Darker Theme Green on hover */
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
            padding: 0; /* Remove internal padding, items will have it */
        }

        .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f8f9fa; /* Very light separator */
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .notification-item:hover {
            background-color: #f1f1f1;
        }

        .notification-item.unread {
            background-color: #f7fff6; /* Very light theme-related background for unread */
            border-left: 3px solid #19860f; /* Theme green indicator */
            padding-left: calc(1rem - 3px); /* Adjust padding due to border */
            font-weight: 500;
        }

        .notification-item.unread p {
            color: #0f5132; /* Darker text for unread content */
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

    <!-- Sidebar (FROM CODE A) -->
    <nav class="sidebar">
        <!-- Logo and Text (Consistent with Code A) -->
        <a href="ProvincialAgriHome.html" class="header-brand">
            <!-- Using the better logo and name from Code A structure -->
            <img src="../photos/logo.png" alt="Department of Agriculture Logo" />
            <div>Agriconnect</div>
        </a>

        <!-- Menu Label (Consistent) -->
        <div class="sidebar-menu-label">Main Menu</div>

        <ul class="nav flex-column">
            <li class="nav-item"><a href="farmer-dashboard.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="farmer-apply_for_assistance.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i>Apply for Assistance</a></li>
            <li class="nav-item"><a href="farmer-planting_status.php" class="nav-link"><i class="fas fa-leaf"></i> Planting Status</a></li>
            <li class="nav-item"><a href="farmer-claim_history.php" class="nav-link"><i class="fas fa-history"></i> Claim History</a></li>
            <li class="nav-item"><a href="farmer-announcement.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li class="nav-item"><a href="farmer-my_profile.php" class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a></li>
        </ul>
        <!-- Logout Section (Consistent - Moved from Header in Code B) -->
        <div class="sidebar-logout">
            <a href="farmers-logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Header (FROM CODE A/B Combination) -->
    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
        <!-- Sidebar Toggle Button (FROM CODE A) -->
        <button id="sidebarToggleBtn" class="btn btn-link p-0 text-dark" title="Toggle Sidebar" style="font-size: 1.5rem;">
            <i class="fas fa-bars"></i>
        </button>
        <!-- Right side alignment wrapper -->
        <div class="d-flex align-items-center">
            <!-- Greeting (Consistent) -->
            <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>

            <!-- Notification Bell (FROM CODE B) -->
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
            <!-- Logout Button Removed from Header (moved to sidebar) -->
        </div>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">

            <h1 class="page-title">Dashboard</h1>
            <p class="text-muted mb-4 dashboard-description">
                Here's a quick overview of your activities and important updates.
            </p>

            <div class="row">
                <!-- Announcements Card with Carousel (FROM CODE B, with CODE A styling) -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-bullhorn me-2"></i>Latest Announcements</h5>
                            <p class="card-text text-muted small">
                                Stay updated with government programs, advisories, and disaster alerts here.
                            </p>

                            <?php if (!empty($announcements)) : ?>
                                <?php
                                $chunks = array_chunk($announcements, 3);
                                ?>
                                <div id="announcementCarousel" class="carousel slide flex-grow-1" data-bs-ride="carousel" data-bs-interval="8000">
                                    <div class="carousel-inner">
                                        <?php foreach ($chunks as $chunkIndex => $chunk) : ?>
                                            <div class="carousel-item <?php echo $chunkIndex === 0 ? 'active' : ''; ?>">
                                                <ul class="list-unstyled small mb-0">
                                                    <?php foreach ($chunk as $announcement) : ?>
                                                        <li class="mb-3">
                                                            <i class="fas fa-circle-info text-info me-2"></i>
                                                            <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                                                            <br>
                                                            <span class="text-muted d-block">
                                                                <?php echo date('F j, Y', strtotime($announcement['publish_date'])); ?>
                                                            </span>
                                                            <p class="mb-0">
                                                                <?php echo htmlspecialchars(substr($announcement['content'], 0, 80)); ?>...
                                                            </p>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <?php if (count($announcements) > 3) : ?>
                                        <!-- Carousel controls only if more than 3 announcements -->
                                        <button class="carousel-control-prev" type="button" data-bs-target="#announcementCarousel" data-bs-slide="prev">
                                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Previous</span>
                                        </button>
                                        <button class="carousel-control-next" type="button" data-bs-target="#announcementCarousel" data-bs-slide="next">
                                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Next</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php else : ?>
                                <p class="text-muted small mb-3 flex-grow-1">No recent announcements.</p>
                            <?php endif; ?>

                            <a href="farmer-announcement.php" class="btn btn-theme mt-auto">View All Announcements</a>
                        </div>
                    </div>
                </div>

                <!-- Subsidy Status Card (Dynamic Data from CODE A) -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-hand-holding-usd me-2"></i>Subsidy Status</h5>
                            <p class="card-text text-muted small">
                                Check the status of your latest assistance applications.
                            </p>
                            <div class="mb-3 flex-grow-1">
                                <!-- Dynamic Application Statuses -->
                                <?php if (!empty($latest_applications)) : ?>
                                    <?php foreach ($latest_applications as $app) : ?>
                                        <p class="mb-1 card-text">
                                            <?php echo $app['type']; ?>:
                                            <span class="status-badge <?php echo get_status_class($app['status']); ?>">
                                                <?php echo $app['status']; ?>
                                            </span>
                                        </p>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <p class="mb-1 text-muted card-text">No recent applications found.</p>
                                <?php endif; ?>
                            </div>
                            <!-- Updated link to claim history (as per CODE A) -->
                            <a href="farmer-claim_history.php" class="btn btn-theme mt-auto">Go to Claim History</a>
                        </div>
                    </div>
                </div>

                <!-- Crop Monitoring Card (FROM CODE A/B) -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-seedling me-2"></i>Crop Monitoring</h5>
                            <p class="card-text text-muted small">
                                Keep track of your crop's progress and update planting status.
                            </p>
                            <ul class="list-unstyled small mb-3 flex-grow-1">
                                <?php if (!empty($latest_crop_status)) : ?>
                                    <li class="card-text">
                                        <i class="fas fa-calendar-check text-success me-2"></i>
                                        Last update for <strong><?php echo htmlspecialchars($latest_crop_status['crop_identifier']); ?></strong>:
                                        <span class="fw-bold"><?php echo htmlspecialchars($latest_crop_status['status']); ?></span>
                                        <br>
                                        <span class="text-muted small ms-4">(<?php echo date('F j, Y', strtotime($latest_crop_status['update_date'])); ?>)</span>
                                    </li>
                                <?php else : ?>
                                    <li class="card-text">No crop monitoring data available. Add your first crop!</li>
                                <?php endif; ?>
                                <li class="card-text"><i class="fas fa-hourglass-half text-warning me-2"></i>Reminder: Regularly update your crop status.</li>
                            </ul>
                            <a href="farmer-planting_status.php" class="btn btn-theme mt-auto">View Crop Details</a>
                        </div>
                    </div>
                </div>

                <!-- Apply for Assistance Card (Conditional Logic from CODE A) -->
                <div class="col-md-12 col-lg-12 mt-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-file-invoice me-2"></i>Apply for New Assistance</h5>
                            <p class="card-text text-muted small">
                                Request support for seeds, fertilizer, fuel, and other farming needs. Browse available programs.
                            </p>
                            <p class="card-text">
                                <span class="fw-bold">Available Programs:</span> Seed Subsidy Program, Agricultural Loan Assistance, Farm Equipment Grant.
                            </p>

                            <?php if ($has_pending_application) : ?>
                                <!-- Alert structure from CODE A -->
                                <div class="alert-custom-warning mt-3 mb-3" role="alert">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <div>
                                        <h6 class="alert-heading mb-1 text-warning">Pending Application!</h6>
                                        You cannot apply for new assistance while a current request is <span class="fw-bold">Pending</span>. Please wait for your existing application to be Approved, Claimed, or Rejected.
                                    </div>
                                </div>
                                <button type="button" class="btn btn-theme mt-auto disabled" disabled>Start New Application</button>
                            <?php else : ?>
                                <a href="farmer-apply_for_assistance.php" class="btn btn-theme mt-auto">Start New Application</a>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript for Sidebar Toggle (FROM CODE A) -->
    <script>
        // JavaScript to toggle sidebar collapse and preserve state using localStorage
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('main');
        const header = document.querySelector('.card-header-custom');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        // sidebarLinks variable is kept but its click listener is removed

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

        // --- IMPROVEMENT: Apply saved state on page load ---
        const isCollapsed = localStorage.getItem('sidebarCollapsed');
        if (isCollapsed === 'true') {
            // Apply collapsed state without saving back to localStorage
            sidebar.classList.add('collapsed');
            mainContent.classList.add('collapsed');
            header.classList.add('collapsed');
        }

        // Toggle button functionality (now uses state saving)
        toggleBtn.addEventListener('click', function() {
            if (sidebar.classList.contains('collapsed')) {
                openSidebar();
            } else {
                collapseSidebar();
            }
        });

        // Retain notification bell JavaScript from Code B (assuming it was available from '../includes/notification_bell.php' or was standard setup)
        // If the bell logic is missing, it should be added here or in the included file.
        // Assuming minimal setup for notification bell based on Code B's HTML:
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

        // Simulate initial notification load (in a real app, this would be an API call)
        document.addEventListener('DOMContentLoaded', () => {
             // Simulate loading with 2 unread announcements
             const list = document.getElementById('notificationList');
             const badge = document.getElementById('notificationBadge');

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
        });
    </script>
</body>

</html>