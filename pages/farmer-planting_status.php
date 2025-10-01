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
    <title>Farmer Account - Planting Status</title>
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
        }
        .sidebar .nav-item .collapse .nav-link.active {
            background-color: #fff; /* Active submenu item background */
            color: #19860f;
            font-weight: 600;
        }
        .sidebar .nav-item .collapse .nav-link:hover:not(.active) {
            background-color: #146c0b; /* Hover for submenu item */
            color: #fff;
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

        /* --- Custom Elements for Planting Status --- */
        .alert-custom-warning {
            background-color: #fff3cd; /* Light yellow */
            border-color: #ffeeba;
            color: #856404;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        .alert-custom-warning i {
            margin-right: 10px;
            font-size: 1.5rem;
            color: #ffc107; /* Warning yellow icon */
        }
        .alert-custom-warning .alert-heading {
            color: #856404;
            font-weight: 600;
        }

        .list-unstyled li {
            font-size: 0.95rem;
            color: #555;
            margin-bottom: 0.5rem;
        }
        .list-unstyled li i {
            width: 20px; /* Align icons */
            text-align: center;
        }

        .form-label {
            font-weight: 500;
            color: #333;
        }

        .form-check-label {
            font-size: 1rem;
            color: #444;
        }

        .form-check-input:checked {
            background-color: #19860f;
            border-color: #19860f;
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
                <!-- Data-bs-toggle added here -->
                <a href="#cropMonitoringSubmenu" data-bs-toggle="collapse" class="nav-link d-flex justify-content-between align-items-center active" aria-expanded="true">
                    <div><i class="fas fa-seedling"></i> Crop Monitoring</div>
                    <i class="fas fa-chevron-down fa-xs"></i>
                </a>
                <div class="collapse show" id="cropMonitoringSubmenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a href="farmer-planting_status.php" class="nav-link active">
                                <i class="fas fa-leaf"></i> Planting Status
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="farmer-progress_tracking.php" class="nav-link">
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
            <h2 class="page-title"><i class="fas fa-seedling me-2"></i>Planting Status</h2>
            <p class="text-muted mb-4">Update your crop's planting progress and check for alerts.</p>

            <div class="row">
                <!-- Reminders/Alerts Card -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-bell me-2"></i>Reminders & Alerts</h5>
                            <div class="alert-custom-warning mb-3" role="alert">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <h6 class="alert-heading mb-1">Action Required!</h6>
                                    Please update the planting status for your <strong class="text-dark">Corn crop</strong> this week.
                                </div>
                            </div>
                            <p class="text-muted small">Upcoming alerts:</p>
                            <ul class="list-unstyled mb-0">
                                <li><i class="fas fa-clock text-info me-2"></i> Fertilizer application reminder for Rice (next week).</li>
                                <li><i class="fas fa-cloud-showers-heavy text-primary me-2"></i> Weather advisory: Possible heavy rains in 3 days.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Planting Status Card -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-clipboard-check me-2"></i>Update Planting Status</h5>
                            <form>
                                <div class="mb-3">
                                    <label for="cropSelect" class="form-label">Select Crop:</label>
                                    <select class="form-select" id="cropSelect" aria-label="Select Crop">
                                        <option selected>Choose...</option>
                                        <option value="rice">Rice (Field 1)</option>
                                        <option value="corn">Corn (Field 2)</option>
                                        <option value="vegetables">Vegetables (Plot 3)</option>
                                    </select>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="plantingStatus" id="planted" value="Planted">
                                    <label class="form-check-label" for="planted">
                                        ✅ Seeds have been planted
                                    </label>
                                </div>

                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="plantingStatus" id="notPlanted" value="Not Planted">
                                    <label class="form-check-label" for="notPlanted">
                                        ❌ Seeds not yet planted
                                    </label>
                                </div>

                                <div class="mb-3">
                                    <label for="photoUpload" class="form-label">Upload Crop Photo (optional)</label>
                                    <input class="form-control" type="file" id="photoUpload">
                                    <div class="form-text">Max file size 5MB. Accepted formats: JPG, PNG.</div>
                                </div>

                                <button type="submit" class="btn btn-theme w-100"><i class="fas fa-upload me-2"></i>Submit Update</button>
                            </form>
                        </div>
                    </div>
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
                // Ensure the parent collapse is shown if an active link is inside it
                var parentCollapse = activeSublink.closest('.collapse');
                if (parentCollapse) {
                    new bootstrap.Collapse(parentCollapse, { toggle: false }).show();
                    // Also, mark the main submenu toggle as active if any sub-item is active
                    var parentToggleLink = document.querySelector('a[href="#' + parentCollapse.id + '"]');
                    if(parentToggleLink) {
                        parentToggleLink.classList.add('active');
                    }
                }
            }
        });
    </script>
</body>
</html>