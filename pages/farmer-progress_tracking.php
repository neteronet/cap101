<?php
session_start(); // Start the session at the very beginning of the script

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("location: farmers-login.php");
    exit();
}

// Retrieve the user's name from the session.
$display_name = $_SESSION['name'] ?? 'Farmer'; // Fallback to 'Farmer' if not set

$servername = "localhost";
$db_username = "root"; // Your database username
$db_password = "";     // Your database password
$dbname = "cap101"; // Your database name

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
} else {
    $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($fetched_db_name);
    $stmt->fetch();
    if ($fetched_db_name) {
        $display_name = $fetched_db_name;
    }
    $stmt->close();
    $conn->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Account - Progress Tracking</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom Styles -->
    <style>
        body {
            font-family: "Poppins", sans-serif;
            background: #f8f9fa;
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            margin: 0;
        }

        /* --- Sidebar Styles (from dashboard) --- */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: #19860f; /* Main green */
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
            transition: all 0.2s ease-in-out;
        }

        .sidebar .nav-link i {
            margin-right: 8px;
            font-size: 1rem;
        }

        .sidebar .nav-link.active {
            background-color: #fff; /* Active link background */
            color: #19860f; /* Active link text color */
            font-weight: 600;
        }

        .sidebar .nav-link:hover:not(.active) {
            background-color: #146c0b; /* Darker green on hover */
            color: #fff;
        }

        /* Submenu styles */
        .sidebar .nav-item .collapse .nav-link {
            padding-left: 2.5rem; /* Indent for submenu items */
            background-color: #19860f; /* Inherit parent background */
            color: #fff;
            font-size: 0.95rem;
            /* Ensure submenu items fill the full width for active state */
            padding-right: 1rem;
        }
        /* Active style for submenu items - now applies to the full width */
        .sidebar .nav-item .collapse .nav-link.active {
            background-color: #fff; /* Active submenu item background */
            color: #19860f;
            font-weight: 600;
            /* Ensure full width highlight */
            border-radius: 0;
        }
        .sidebar .nav-item .collapse .nav-link:hover:not(.active) {
            background-color: #146c0b; /* Hover for submenu item */
            color: #fff;
        }

        /* Specific style for the dropdown toggle link itself */
        .sidebar .nav-link.dropdown-toggle-custom {
            /* No active background for the toggle itself, only for the items within */
            background-color: transparent;
            color: #fff; /* Ensure it stays white */
        }
        .sidebar .nav-link.dropdown-toggle-custom:hover {
            background-color: #146c0b; /* Darker green on hover */
            color: #fff;
        }
        /* Style for the chevron icon to rotate */
        .sidebar .nav-link .fa-chevron-down {
            transition: transform 0.2s ease-in-out;
        }
        .sidebar .nav-link.dropdown-toggle-custom[aria-expanded="true"] .fa-chevron-down {
            transform: rotate(180deg);
        }

        .sidebar .header-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            margin-bottom: 1rem;
            padding: 0 1rem; /* Padding for the brand area */
        }

        .sidebar .header-brand img {
            width: 100%;
            max-width: 120px;
            height: auto;
            background: #19860f; /* Match sidebar background */
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

        /* --- Fixed Top Header (from dashboard) --- */
        .card-header-custom {
            position: fixed;
            top: 0;
            left: 250px; /* Aligned with main content start */
            right: 0;
            height: 56px; /* Standard Bootstrap navbar height */
            background-color: #fff;
            color: #19860f; /* Green text for branding/user info */
            padding: 0 1.25rem;
            font-weight: 500;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: flex-end; /* Align items to the right */
            z-index: 1060; /* Higher than sidebar */
            border-bottom: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); /* Subtle shadow */
        }

        .header-brand span { /* This style is for the "AntiqueProv Agri" in the original header. Not used in this layout. */
            font-size: 1rem;
            font-weight: 600;
            color: #19860f;
        }

        .logout-btn {
            background: #ff4b2b; /* Red */
            color: #fff;
            border: none;
            padding: 6px 14px;
            font-size: 14px;
            border-radius: 20px;
            transition: background 0.2s ease;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: #e04325; /* Darker red on hover */
        }

        /* --- Main Content Area --- */
        main {
            margin-left: 250px; /* Space for the sidebar */
            padding: 1rem 2rem 2rem 2rem;
            padding-top: 72px; /* Space for the fixed top header */
            background: #f8f9fa;
            min-height: 100vh;
        }

        .page-title {
            font-size: 1.8rem; /* Adjusted for consistency */
            font-weight: 600;
            color: #19860f; /* Green */
            margin-bottom: 1rem;
        }

        .card {
            border-radius: 0.5rem; /* Consistent border-radius */
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05); /* Consistent shadow */
            margin-bottom: 1rem;
        }

        .card-title {
            color: #19860f; /* Green title for cards */
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 1.25rem; /* Consistent with dashboard */
        }

        /* --- Progress Tracking Specific Styles --- */
        .progress-bar-custom {
            background-color: #28a745; /* Success green */
        }
        .progress-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        .progress-text {
            font-size: 0.9em;
            color: #6c757d;
        }

        .btn-theme { /* Re-using the btn-theme from dashboard for consistency */
            background-color: #19860f;
            color: #fff;
            font-size: 15px;
            padding: 10px 20px;
            border-radius: 4px;
            transition: background 0.2s ease;
            border: none; /* Ensure no default border */
        }

        .btn-theme:hover {
            background-color: #146c0b;
            color: #fff; /* Keep text white on hover */
        }

        .btn-outline-info {
            color: #17a2b8;
            border-color: #17a2b8;
            transition: all 0.2s ease;
        }
        .btn-outline-info:hover {
            background-color: #17a2b8;
            color: #fff;
        }

        .btn-outline-primary {
            color: #007bff;
            border-color: #007bff;
            transition: all 0.2s ease;
        }
        .btn-outline-primary:hover {
            background-color: #007bff;
            color: #fff;
        }

    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <a href="ProvincialAgriHome.html" class="header-brand">
            <img src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Department of Agriculture Logo" />
            <div>Province of Antique</div>
        </a>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="farmer-dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="farmer-my_profile.php" class="nav-link">
                    <i class="fas fa-user-circle"></i> My Profile
                </a>
            </li>
            <li class="nav-item">
                <a href="farmer-subsidy_status.php" class="nav-link">
                    <i class="fas fa-hand-holding-usd"></i> Subsidy Status
                </a>
            </li>
            <li class="nav-item">
                <a href="farmer-announcement.php" class="nav-link">
                    <i class="fas fa-bullhorn"></i> Announcements
                </a>
            </li>
            <li class="nav-item">
                <a href="farmer-apply_for_assistance.php" class="nav-link">
                    <i class="fas fa-file-invoice"></i> Apply for Assistance
                </a>
            </li>
            <li class="nav-item">
                <!-- Data-bs-toggle added here - using dropdown-toggle-custom for no active background on parent -->
                <a href="#cropMonitoringSubmenu" data-bs-toggle="collapse" class="nav-link dropdown-toggle-custom d-flex justify-content-between align-items-center" aria-expanded="true">
                    <div><i class="fas fa-seedling"></i> Crop Monitoring</div>
                    <i class="fas fa-chevron-down fa-xs"></i>
                </a>
                <div class="collapse show" id="cropMonitoringSubmenu">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a href="farmer-planting_status.php" class="nav-link">
                                <i class="fas fa-leaf"></i> Planting Status
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="farmer-progress_tracking.php" class="nav-link active">
                                <i class="fas fa-chart-line"></i> Progress Tracking
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
    </nav>

    <!-- Header (fixed to top right) -->
    <div class="card-header card-header-custom d-flex justify-content-end align-items-center">
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <button class="logout-btn" onclick="location.href='farmers-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Content -->
    <main>
        <div class="container">
            <h2 class="page-title"><i class="fas fa-chart-line me-2"></i>Progress Tracking</h2>
            <p class="text-muted mb-4">Visual overview of your active crops.</p>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-chart-line me-2"></i>Active Crop Progress</h5>

                    <div class="mb-4 pb-3 border-bottom"> <!-- Added border-bottom for separation -->
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="progress-label">Rice (Field 1)</span>
                            <span class="progress-text">60 Days since planting</span>
                        </div>
                        <div class="progress" role="progressbar" aria-label="Rice Progress" aria-valuenow="60" aria-valuemin="0" aria-valuemax="120" style="height: 20px;">
                            <div class="progress-bar progress-bar-custom" style="width: 50%;">Flowering Stage (50%)</div>
                        </div>
                        <small class="text-muted d-block mt-1">Expected harvest in ~60 days.</small>
                        <div class="mt-2">
                            <button class="btn btn-outline-info btn-sm">View Details</button>
                            <button class="btn btn-outline-primary btn-sm">Add Update</button>
                        </div>
                    </div>

                    <div class="mb-4 pb-3 border-bottom"> <!-- Added border-bottom for separation -->
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="progress-label">Corn (Field 2)</span>
                            <span class="progress-text">10 Days since planting</span>
                        </div>
                        <div class="progress" role="progressbar" aria-label="Corn Progress" aria-valuenow="10" aria-valuemin="0" aria-valuemax="90" style="height: 20px;">
                            <div class="progress-bar progress-bar-custom bg-info" style="width: 11%;">Seedling Stage (11%)</div>
                        </div>
                        <small class="text-muted d-block mt-1">Requires watering and initial fertilization.</small>
                        <div class="mt-2">
                            <button class="btn btn-outline-info btn-sm">View Details</button>
                            <button class="btn btn-outline-primary btn-sm">Add Update</button>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <p class="text-muted">No other active crops being tracked.</p>
                    </div>

                    <a href="#" class="btn btn-theme mt-3"><i class="fas fa-plus me-1"></i> Add New Crop for Tracking</a>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Optional: Keep submenu expanded if an item within it is active
        document.addEventListener('DOMContentLoaded', function() {
            var cropMonitoringSubmenu = document.getElementById('cropMonitoringSubmenu');
            var activeSublink = cropMonitoringSubmenu.querySelector('.nav-link.active');
            if (activeSublink) {
                var parentCollapse = activeSublink.closest('.collapse');
                if (parentCollapse) {
                    new bootstrap.Collapse(parentCollapse, { toggle: false }).show();
                    // Also, ensure the parent toggle link updates its aria-expanded state if needed
                    var parentToggleLink = document.querySelector('a[href="#' + parentCollapse.id + '"]');
                    if (parentToggleLink) {
                        parentToggleLink.setAttribute('aria-expanded', 'true');
                    }
                }
            }
        });
    </script>
</body>
</html>