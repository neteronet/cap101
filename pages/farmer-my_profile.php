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
// PHP CHANGE: Farmer ID is now displayed prominently
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

// --- 2. Fetch farmer's profile data ---
$farmer_data = null;
if (isset($_SESSION['user_id'])) {
    // Removed 'status' from the SELECT query
    $stmt_farmer = $conn->prepare("SELECT
                                    farmer_id, rsbsa_id, first_name, middle_name, last_name,
                                    address, contact_number, land_details,
                                    age, gender, civil_status, crop
                                   FROM Farmers
                                   WHERE user_id = ?");
    if ($stmt_farmer) {
        $stmt_farmer->bind_param("i", $_SESSION['user_id']);
        $stmt_farmer->execute();
        $result = $stmt_farmer->get_result();
        $farmer_data = $result->fetch_assoc();
        $stmt_farmer->close();

        // If land_details is a JSON string, decode it
        if ($farmer_data && isset($farmer_data['land_details']) && !empty($farmer_data['land_details'])) {
            $farmer_data['land_details_decoded'] = json_decode($farmer_data['land_details'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decoding error for farmer_id " . ($farmer_data['farmer_id'] ?? 'unknown') . ": " . json_last_error_msg() . " Raw data: " . $farmer_data['land_details']);
                $farmer_data['land_details_decoded'] = []; // Fallback to empty array on error
            }
        } else if ($farmer_data) {
            $farmer_data['land_details_decoded'] = []; // Initialize if no land_details or empty string
        }
    } else {
        error_log("Failed to prepare farmer data statement: " . $conn->error);
    }
}

$conn->close();

// Fallback for cases where farmer_data couldn't be fetched or doesn't exist
if (!$farmer_data) {
    $farmer_data = [
        'first_name' => 'N/A',
        'middle_name' => '',
        'last_name' => 'N/A',
        'rsbsa_id' => 'N/A',
        'address' => 'N/A',
        'contact_number' => 'N/A',
        'land_details' => null,
        'land_details_decoded' => [],
        'age' => 'N/A',
        'gender' => 'N/A',
        'civil_status' => 'N/A',
        'crop' => 'N/A'
    ];
}

// Construct full name for display in the profile body
$full_name_profile = htmlspecialchars($farmer_data['first_name'] . ' ' .
    (!empty($farmer_data['middle_name']) ? substr($farmer_data['middle_name'], 0, 1) . '. ' : '') .
    $farmer_data['last_name']);

// Use the fetched age, gender, civil_status, and crop directly
$age = htmlspecialchars($farmer_data['age'] ?? 'N/A');
$gender = htmlspecialchars($farmer_data['gender'] ?? 'N/A');
$civil_status = htmlspecialchars($farmer_data['civil_status'] ?? 'N/A');
$crop = htmlspecialchars($farmer_data['crop'] ?? 'N/A');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Profile - Farmer Portal</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts (UPDATED FOR CONSISTENCY) -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <style>
        /* --- START CONSISTENT DESIGN STYLES (FROM DASHBOARD) --- */
        body {
            font-family: "Poppins", sans-serif;
            background: #f8f9fa;
            font-size: 16px;
            line-height: 1.6;
            color: #212529; /* Adjusted for better reading contrast */
            margin: 0;
        }

        /* --- Sidebar Styles (CONSISTENT) --- */
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
            font-family: "Be Vietnam Pro", sans-serif; /* Consistent Font */
        }
        
        .sidebar.collapsed {
            left: -250px;
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

        /* --- Fixed Top Header (CONSISTENT) --- */
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
            justify-content: space-between; /* Changed to space-between for toggle button */
            z-index: 1060;
            border-bottom: 1px solid #ddd;
            transition: left 0.3s ease;
            font-family: "Be Vietnam Pro", sans-serif; /* Consistent Font */
        }

        .card-header-custom.collapsed {
            left: 0;
        }

        /* --- Main Content Area (CONSISTENT) --- */
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
        
        /* 1. Color Palette Standardization: Links */
        a {
            color: #19860f;
        }

        a:hover {
            color: #146c0b;
        }

        /* 5. Typography Consistency: Card Titles & Headings */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .card-title,
        .modal-title,
        .page-title {
            font-family: "Be Vietnam Pro", sans-serif; /* Consistent Font */
            color: #0f5132;
            /* Dark Green */
        }
        
        /* NEW: Style for the Page Title (Dashboard & Profile) */
        .page-title {
            font-size: 1.5rem; 
            font-weight: 600; 
            margin-bottom: 0.5rem;
        }
        
        /* NEW: Style for the Dashboard/Profile Description Paragraph */
        .dashboard-description {
            font-size: 0.875rem; /* 14px */
        }
        
        /* NEW: Explicit Card Title Size for Consistency */
        .card-title {
            font-size: 1.25rem; 
            font-weight: 600;
        }
        
        /* NEW: Explicit Standard Card Text Size for Consistency (0.9375rem = 15px) */
        .card-text, 
        .card-body p:not(.card-title):not(.summary-stat p), 
        .list-unstyled li {
            font-size: 0.9375rem; /* ~15px for better readability in main content */
        }
        .card-text.small, 
        .list-unstyled.small li, 
        .card-text.text-muted.small {
            font-size: 0.875rem !important; /* Keep 14px for elements explicitly marked as small */
        }


        /* 2. Button and Alert Unification: Button Theme */
        .btn-theme {
            background-color: #19860f;
            color: #fff;
            border-color: #19860f;
            font-family: "Be Vietnam Pro", sans-serif; /* Consistent Font */
        }

        .btn-theme:hover {
            background-color: #146c0b;
            border-color: #146c0b;
            color: #fff;
        }
        
        /* 2. Button and Alert Unification: Outline Button Theme */
        .btn-outline-theme {
            color: #19860f;
            border-color: #19860f;
            font-family: "Be Vietnam Pro", sans-serif; /* Consistent Font */
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

        /* Custom alerts using consistent palette (from dashboard) */
        .alert-custom-success {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
            border-radius: .375rem;
            padding: .75rem 1rem;
        }

        .alert-custom-danger,
        .alert-custom-critical {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
            border-radius: .375rem;
            padding: .75rem 1rem;
        }

        .alert-custom-warning {
            background-color: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
            border-radius: .375rem;
            padding: .75rem 1rem;
            display: flex;
            gap: .75rem;
            align-items: flex-start;
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 0.9375rem; 
        }

        .alert-custom-info {
            background-color: #cff4fc;
            color: #055160;
            border: 1px solid #b6effb;
            border-radius: .375rem;
            padding: .75rem 1rem;
            display: flex;
            gap: .75rem;
            align-items: flex-start;
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 0.9375rem;
        }

        .alert-custom-warning i,
        .alert-custom-info i,
        .alert-custom-danger i {
            margin-top: .2rem;
        }
        
        #sidebarToggleBtn {
            color: #0f5132;
        }

        #sidebarToggleBtn:hover {
            color: #146c0b;
        }

        /* General card styling for consistency */
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05); /* Default shadow */
            margin-bottom: 1.5rem;
            border: 1px solid #ddd;
        }
        /* --- END CONSISTENT DESIGN STYLES (FROM DASHBOARD) --- */

        /* --- PROFILE-SPECIFIC STYLES (Adjusted for Harmony) --- */
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1.5rem;
        }

        .profile-header img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #19860f; /* Add a subtle theme border */
        }

        .profile-header h4 {
            font-size: 1.5rem;
            color: #0f5132; /* Consistent with h1-h6 color */
            margin-bottom: 0.3rem;
        }

        .info-label {
            font-weight: 600;
            margin-right: 0.5rem;
            color: #555;
            min-width: 150px;
            display: inline-block;
            font-size: 0.9375rem; /* ~15px, consistent with body text */
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f5132; /* Consistent with h1-h6 color */
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            font-family: "Be Vietnam Pro", sans-serif; /* Consistent Font */
        }

        .card-body p {
            margin-bottom: 0.5rem;
            font-size: 0.9375rem; /* ~15px, consistent with body text */
        }

        /* Update contact button styling (Kept unique for its compact size) */
        .update-contact-btn {
            background: #19860f;
            color: #fff;
            border: none;
            padding: 4px 10px;
            font-size: 11px;
            border-radius: 12px;
            transition: all 0.2s ease;
            cursor: pointer;
            font-weight: 500;
            margin-left: 8px;
            vertical-align: middle;
            text-decoration: none; /* Ensure it looks like a button */
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .update-contact-btn:hover {
            background: #146c0b;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(25, 134, 15, 0.3);
            color: #fff; /* Ensure hover text remains white */
        }

        .contact-number-wrapper {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        /* Styles for the Summary/Badges section */
        .summary-stat {
            padding: 0.5rem 0;
        }
        .summary-stat .fw-bold {
            font-size: 1.2rem;
            color: #19860f; /* Theme color for values */
            font-family: "Be Vietnam Pro", sans-serif;
        }
        
        .summary-stat .text-secondary {
            color: #6c757d !important;
        }
        
        /* Land details card for empty state - uses alert-custom-info for consistency */
        /* The alert-custom-info is now defined in the consistent styles, keeping the specific structure here */
        .no-land-details-info {
            /* These styles are no longer needed as the HTML uses .alert-custom-info */
        }
    </style>
</head>

<body>

    <!-- Sidebar (CONSISTENT DESIGN) -->
    <nav class="sidebar">
        <!-- HTML structure for the logo and text is consistent with dashboard -->
        <a href="ProvincialAgriHome.html" class="header-brand">
            <img src="../photos/logo.png" alt="Department of Agriculture Logo" />
            <div>Agriconnect</div>
        </a>

        <!-- START NEW INSERTION: Main Menu Label (CONSISTENT) -->
        <div class="sidebar-menu-label">Main Menu</div>
        <!-- END NEW INSERTION -->

        <ul class="nav flex-column">
            <li class="nav-item"><a href="farmer-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="farmer-apply_for_assistance.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i>Apply for Assistance</a></li>
            <li class="nav-item"><a href="farmer-planting_status.php" class="nav-link"><i class="fas fa-leaf"></i> Planting Status</a></li>
            <li class="nav-item"><a href="farmer-claim_history.php" class="nav-link"><i class="fas fa-history"></i> Claim History</a></li>
            <li class="nav-item"><a href="farmer-announcement.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li class="nav-item"><a href="farmer-my_profile.php" class="nav-link active"><i class="fas fa-user-circle"></i> My Profile</a></li>
        </ul>
        <!-- Sidebar Logout Section (CONSISTENT DESIGN) -->
        <div class="sidebar-logout">
            <a href="farmers-logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Header (CONSISTENT DESIGN) -->
    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
        <!-- New Toggle Button (CONSISTENT) -->
        <button id="sidebarToggleBtn" class="btn btn-link p-0 text-dark" title="Toggle Sidebar" style="font-size: 1.5rem;">
            <i class="fas fa-bars"></i>
        </button>
        <!-- Greeting -->
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container-fluid">
        
            <!-- PAGE TITLE (CONSISTENT) -->
            <h1 class="page-title">My Profile</h1>
            <p class="text-muted mb-4 dashboard-description">
                Manage your personal information, address, and land details.
            </p>
            <!-- END PAGE TITLE -->

            <!-- MAIN PROFILE CARD: COMBINED HEADER AND SUMMARY -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    
                    <!-- 1. Profile Header (Enhanced with Farmer ID and Address Icon) -->
                    <div class="profile-header">
                        <img src="../photos/Avatar.png" alt="Farmer Photo">
                        <div>
                            <h4><?php echo $full_name_profile; ?></h4>
                            <!-- NEW: Added Farmer ID for quick reference -->
                            <p class="mb-1 text-muted small">Farmer ID: <strong><?php echo htmlspecialchars($farmer_id_display); ?></strong></p>
                            <p class="mb-1 text-muted small">RSBSA ID: <strong><?php echo htmlspecialchars($farmer_data['rsbsa_id']); ?></strong></p>
                            <!-- Address is kept simple but with an icon -->
                            <p class="mb-0 text-muted small"><i class="fas fa-map-marker-alt me-1 text-success"></i> <?php echo htmlspecialchars($farmer_data['address']); ?></p>
                        </div>
                    </div>

                    <!-- 2. Summary Badges/Stats Row (Makes personal info more prominent) -->
                    <h5 class="section-title mb-3"><i class="fas fa-chart-line me-2"></i>Farmer Snapshot</h5>
                    <!-- Consistency: Added border rounded p-3 for better visual separation -->
                    <div class="row text-center mb-4 border rounded p-3 bg-light">
                        <!-- Badge 1: Primary Crop -->
                        <div class="col-md-3 col-6 summary-stat border-end">
                            <p class="text-secondary mb-1 small fw-semibold"><i class="fas fa-seedling me-1"></i> PRIMARY CROP</p>
                            <h5 class="fw-bold"><?php echo $crop; ?></h5>
                        </div>
                        <!-- Badge 2: Age -->
                        <div class="col-md-3 col-6 summary-stat">
                            <p class="text-secondary mb-1 small fw-semibold"><i class="fas fa-calendar-alt me-1"></i> AGE</p>
                            <h5 class="fw-bold"><?php echo $age; ?></h5>
                        </div>
                        <!-- Badge 3: Gender -->
                        <!-- Adjusted border class for responsiveness (border-start-md-0 is important) -->
                        <div class="col-md-3 col-6 summary-stat border-top border-top-md-0 border-start mt-3 mt-md-0">
                            <p class="text-secondary mb-1 small fw-semibold"><i class="fas fa-venus-mars me-1"></i> GENDER</p>
                            <h5 class="fw-bold"><?php echo $gender; ?></h5>
                        </div>
                        <!-- Badge 4: Civil Status -->
                        <div class="col-md-3 col-6 summary-stat border-top border-top-md-0 mt-3 mt-md-0">
                            <p class="text-secondary mb-1 small fw-semibold"><i class="fas fa-ring me-1"></i> CIVIL STATUS</p>
                            <h5 class="fw-bold"><?php echo $civil_status; ?></h5>
                        </div>
                    </div>
                    
                    <!-- 3. Contact Information (Refactored) -->
                    <h5 class="section-title mb-3"><i class="fas fa-address-book me-2"></i>Contact Information</h5>
                    <div class="row">
                        <div class="col-md-12">
                            <p class="mb-0">
                                <span class="info-label"><i class="fas fa-phone-alt me-2 text-success"></i>Contact Number:</span>
                                <span class="contact-number-wrapper">
                                    <?php echo htmlspecialchars($farmer_data['contact_number']); ?>
                                    <!-- Changed button to use custom CSS and ensure it looks like a button -->
                                    <a href="farmer-update_contact.php" class="update-contact-btn">
                                        <i class="fas fa-edit"></i> Update Contact
                                    </a>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LAND DETAILS CARD -->
            <h5 class="section-title"><i class="fas fa-map-marked-alt me-2"></i>Land Details</h5>

            <?php if (!empty($farmer_data['land_details_decoded'])): ?>
                <!-- ADDED: shadow-sm for consistency -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <p class="mb-0">
                                    <span class="info-label"><i class="fas fa-location-arrow me-2 text-success"></i>Location:</span> 
                                    <?php echo htmlspecialchars($farmer_data['land_details_decoded']['location'] ?? 'N/A'); ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="mb-0">
                                    <span class="info-label"><i class="fas fa-ruler-combined me-2 text-success"></i>Area:</span> 
                                    <?php echo htmlspecialchars($farmer_data['land_details_decoded']['size'] ?? 'N/A'); ?>
                                </p>
                            </div>
                            <!-- Note: Crop is now displayed in the Summary Snapshot -->
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Changed to use a consistent custom alert style -->
                <div class="alert-custom-info d-flex align-items-center justify-content-center py-4 shadow-sm" role="alert">
                    <i class="fas fa-info-circle me-2 fs-5"></i>
                    <p class="mb-0 fw-semibold">No land details recorded in your profile.</p>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript for Sidebar Toggle (CONSISTENT DESIGN - WITH localStorage) -->
    <script>
        // JavaScript to toggle sidebar collapse and preserve state using localStorage
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
    </script>
</body>

</html>