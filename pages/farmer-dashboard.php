<?php
session_start(); // Start the session at the very beginning of the script

// --- HELPER FUNCTION: Status to CSS Class Mapping ---
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

// --- Fetch Latest Announcements ---
$announcements = [];
$stmt_announcements = $conn->prepare("SELECT title, content, publish_date FROM announcements ORDER BY publish_date DESC LIMIT 1");
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

// --- Fetch Latest Crop Monitoring Status for the logged-in user ---
$latest_crop_status = null;
// You'll need a 'planting_status' or similar table for this
// Assuming a table 'farmer_crops' with columns: crop_id, user_id, crop_name, status, last_update_date
// And 'crop_identifier' is 'crop_name' for this example. Adjust table/column names as per your DB schema.
$stmt_crop_status = $conn->prepare("SELECT crop_identifier, status, update_date FROM planting_status WHERE user_id = ? ORDER BY update_date DESC LIMIT 1");
if ($stmt_crop_status) {
    $stmt_crop_status->bind_param("i", $user_id); // Assuming user_id is an integer
    $stmt_crop_status->execute();
    // Assuming 'crop_identifier' and 'update_date' exist in 'planting_status'
    $stmt_crop_status->bind_result($crop_name, $status, $update_date);
    if ($stmt_crop_status->fetch()) {
        $latest_crop_status = [
            'crop_identifier' => $crop_name,
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

// --- NEW: Fetch Latest Assistance Applications Status for the logged-in user (Subsidy Status Card) ---
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


// --- NEW: Check for Pending Assistance Applications to restrict new applications (THE REQUIRED LOGIC) ---
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

    <!-- Google Fonts -->
    <!-- MODIFIED: Changed font pairing to Be Vietnam Pro (Headings/UI) and Poppins (Body/Content) -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <!-- REMOVED redundant Inter link and replaced with the combined Montserrat/Poppins link above for clean setup -->
   
    <!-- Custom Styles (Copied from farmer-planting_status.php for consistency) -->
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
        .page-title { /* ADDED .page-title HERE */
            /* MODIFIED: Explicitly set headings/titles font to Be Vietnam Pro */
            font-family: "Be Vietnam Pro", sans-serif;
            color: #0f5132;
            /* Dark Green */
        }
        
        /* NEW: Style for the Page Title (Dashboard) */
        .page-title {
            font-size: 1.5rem; /* Reduced from default H1 (2.5rem) to be less dominating */
            font-weight: 600; /* Made it slightly bold for prominence */
            margin-bottom: 0.5rem;
        }
        /* END NEW */
        
        /* NEW: Style for the Dashboard Description Paragraph */
        .dashboard-description {
            font-size: 0.875rem; /* 14px */
        }
        /* END NEW */
        
        /* NEW: Explicit Card Title Size for Consistency */
        .card-title {
            font-size: 1.25rem; /* Standard H5 size, but explicitly set */
            font-weight: 600; /* Added slight boldness */
        }
        /* END NEW */
        
        /* NEW: Explicit Standard Card Text Size for Consistency (0.9375rem = 15px) */
        .card-text, 
        .card-body p:not(.card-title), 
        .list-unstyled li {
            /* Inherits Poppins from body */
            font-size: 0.9375rem; /* ~15px for better readability in main content */
        }
        .card-text.small, 
        .list-unstyled.small li, 
        .card-text.text-muted.small {
            font-size: 0.875rem !important; /* Keep 14px for elements explicitly marked as small */
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
            background-color: #7ab372; /* Lighter shade of theme color for disabled */
            border-color: #7ab372;
            pointer-events: none; /* Ensure no click */
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
        
        /* Bootstrap default badge overrides for consistency */
        .text-info { color: #0dcaf0 !important; }
        .text-success { color: #198754 !important; }
        .text-warning { color: #ffc107 !important; }
        .text-danger { color: #dc3545 !important; }

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
            /* Removing previous override to rely on the custom alert class */
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

    </style>

</head>
<body>

    <!-- Sidebar (CONSISTENT DESIGN) -->
    <nav class="sidebar">
        <!-- Logo and Text (Consistent with planting-status.php) -->
        <a href="ProvincialAgriHome.html" class="header-brand">
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
        <!-- Logout Section (Consistent) -->
        <div class="sidebar-logout">
            <a href="farmers-logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Header (CONSISTENT DESIGN) -->
    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
        <!-- Sidebar Toggle Button (Consistent) -->
        <button id="sidebarToggleBtn" class="btn btn-link p-0 text-dark" title="Toggle Sidebar" style="font-size: 1.5rem;">
            <i class="fas fa-bars"></i>
        </button>
        <!-- Greeting -->
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">

            <h1 class="page-title">Dashboard</h1>
            <p class="text-muted mb-4 dashboard-description">
                Here's a quick overview of your activities and important updates.
            </p>

            <div class="row">
                <!-- Announcements Card (UPDATED CLASSES) -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-bullhorn me-2"></i>Latest Announcements</h5>
                            <p class="card-text text-muted small">
                                Stay updated with government programs, advisories, and disaster alerts here.
                            </p>
                            <ul class="list-unstyled small mb-3">
                                <?php if (!empty($announcements)) : ?>
                                    <?php foreach ($announcements as $announcement) : ?>
                                        <li>
                                            <i class="fas fa-circle-info text-info me-2"></i>
                                            <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                                            <br>
                                            <span class="text-muted"><?php echo date('F j, Y', strtotime($announcement['publish_date'])); ?></span>
                                            <p class="mb-0"><?php echo htmlspecialchars(substr($announcement['content'], 0, 70)); ?>...</p>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <li>No recent announcements.</li>
                                <?php endif; ?>
                            </ul>
                            <a href="farmer-announcement.php" class="btn btn-theme mt-auto">View All Announcements</a>
                        </div>
                    </div>
                </div>

                <!-- Subsidy Status Card (NOW DYNAMICALLY FETCHED) -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-hand-holding-usd me-2"></i>Subsidy Status</h5>
                            <p class="card-text text-muted small">
                                Check the status of your latest assistance applications.
                            </p>
                            <div class="mb-3">
                                <!-- Dynamic Application Statuses -->
                                <?php if (!empty($latest_applications)) : ?>
                                    <?php foreach ($latest_applications as $app) : ?>
                                        <p class="mb-1">
                                            <?php echo $app['type']; ?>: 
                                            <span class="status-badge <?php echo get_status_class($app['status']); ?>">
                                                <?php echo $app['status']; ?>
                                            </span>
                                        </p>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <p class="mb-1 text-muted">No recent applications found.</p>
                                <?php endif; ?>
                            </div>
                            <!-- Updated link to claim history for consistency with sidebar -->
                            <a href="farmer-claim_history.php" class="btn btn-theme mt-auto">Go to Claim History</a>
                        </div>
                    </div>
                </div>

                <!-- Crop Monitoring Card (UPDATED CLASSES) -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-seedling me-2"></i>Crop Monitoring</h5>
                            <p class="card-text text-muted small">
                                Keep track of your crop's progress and update planting status.
                            </p>
                            <ul class="list-unstyled small mb-3">
                                <?php if (!empty($latest_crop_status)) : ?>
                                    <li>
                                        <i class="fas fa-calendar-check text-success me-2"></i>
                                        Last update for <strong><?php echo htmlspecialchars($latest_crop_status['crop_identifier']); ?></strong>:
                                        <span class="fw-bold"><?php echo htmlspecialchars($latest_crop_status['status']); ?></span>
                                        <br>
                                        <span class="text-muted small ms-4">(<?php echo date('F j, Y', strtotime($latest_crop_status['update_date'])); ?>)</span>
                                    </li>
                                <?php else : ?>
                                    <li>No crop monitoring data available. Add your first crop!</li>
                                <?php endif; ?>
                                <li><i class="fas fa-hourglass-half text-warning me-2"></i>Reminder: Regularly update your crop status.</li>
                            </ul>
                            <a href="farmer-planting_status.php" class="btn btn-theme mt-auto">View Crop Details</a>
                        </div>
                    </div>
                </div>

                <!-- Apply for Assistance Card (UPDATED LOGIC AND CLASSES) -->
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
                                <!-- MODIFIED: Changed to use the consistent alert-custom-warning class and structure -->
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

    <!-- JavaScript for Sidebar Toggle (FIXED FOR NAVIGATION) -->
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

        // The 'Optional: Collapse sidebar on link click' block has been removed
        // to prevent the unwanted 'movement' upon navigation.
    </script>
</body>

</html>