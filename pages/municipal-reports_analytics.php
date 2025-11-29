<?php
session_start();

include '../includes/connection.php'; // Ensure this path is correct

if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: municipal-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Mao'; // Default fallback

// Fetch User Name (Security: Uses prepared statement and htmlspecialchars)
$stmt_name = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
if ($stmt_name) {
    $stmt_name->bind_param("i", $user_id);
    $stmt_name->execute();
    $stmt_name->bind_result($db_name);
    $stmt_name->fetch();
    if ($db_name) {
        $display_name = htmlspecialchars($db_name); // Sanitize immediately
    }
    $stmt_name->close();
} else {
    error_log("Failed to prepare statement for user name: " . $conn->error);
}

// Get filter values from GET parameters
$reportType = isset($_GET['reportType']) ? $_GET['reportType'] : 'crop';
$periodFilter = isset($_GET['periodFilter']) ? $_GET['periodFilter'] : 'current';

// Calculate date range based on period
$endDate = date('Y-m-d');
switch ($periodFilter) {
    case 'current':
        $startDate = date('Y-m-d', strtotime('-6 months'));
        break;
    case 'last3m':
        $startDate = date('Y-m-d', strtotime('-3 months'));
        break;
    case 'last6m':
        $startDate = date('Y-m-d', strtotime('-6 months'));
        break;
    case 'yearly':
        $startDate = date('Y-m-d', strtotime('-1 year'));
        break;
    case 'custom':
        // For custom, assume dates are provided, but for now use last 6 months
        $startDate = isset($_GET['startDate']) ? $_GET['startDate'] : date('Y-m-d', strtotime('-6 months'));
        $endDate = isset($_GET['endDate']) ? $_GET['endDate'] : date('Y-m-d');
        break;
    default:
        $startDate = date('Y-m-d', strtotime('-6 months'));
}

// Data fetching initialization
$cropYieldLabels = [];
$cropYieldData = [];

$subsidyApprovedClaimed = 0;
$subsidyApprovedPendingClaim = 0;
$subsidyPendingReview = 0;
$subsidyRejected = 0;

$farmerAge18_25 = 0;
$farmerAge26_35 = 0;
$farmerAge36_45 = 0;
$farmerAge46_55 = 0;
$farmerAge56_65 = 0;
$farmerAge65_plus = 0;

$totalActiveFarmers = 0;
$totalHectaresPlanted = 0;
$pendingSubsidyRequests = 0;
$farmersRegistered = 0;

// NEW: Disaster Report Initialization
$disasterTypeLabels = [];
$disasterTypeData = [];

// Re-establish connection check (Good practice)
if (isset($conn) && $conn->connect_error) { // Check if connection is still valid/open, or re-establish
    // Assuming $servername, $db_username, $db_password, $dbname are defined in connection.php
    $conn = new mysqli($servername, $db_username, $db_password, $dbname);
    if ($conn->connect_error) {
        error_log("Database connection failed for crop data: " . $conn->connect_error);
        // Exit or set default data if connection fails
        die("Connection failed: " . $conn->connect_error);
    }
}


if (isset($conn) && !$conn->connect_error) {
    
    // --- 1. Crop Performance by Type chart (Secured with Prepared Statements) ---
    // Query to count the number of farmers per crop type
    $sql_crop_performance = "SELECT
                                crop as crop_type,
                                COUNT(farmer_id) as total_farmers_count
                            FROM farmers
                            -- Filter out NULL or empty crop entries and apply date filter securely
                            WHERE crop IS NOT NULL AND crop != '' AND created_at BETWEEN ? AND ?
                            GROUP BY crop_type
                            ORDER BY total_farmers_count DESC
                            LIMIT 6";

    $stmt_crop = $conn->prepare($sql_crop_performance);

    if ($stmt_crop) {
        $stmt_crop->bind_param("ss", $startDate, $endDate); // 's' for string (date)
        $stmt_crop->execute();
        $result_crop_performance = $stmt_crop->get_result(); // Get the result set

        if ($result_crop_performance) {
            while ($row = $result_crop_performance->fetch_assoc()) {
                $cropYieldLabels[] = htmlspecialchars($row['crop_type']); // Sanitize crop name
                // Use the count of farmers as the data point
                $cropYieldData[] = $row['total_farmers_count']; 
            }
        }
        $stmt_crop->close();
    } else {
        error_log("Error preparing crop performance statement: " . $conn->error);
    }

    // --- 2. Subsidy Distribution Status chart (Secured with Prepared Statements) ---
    $sql_subsidy_status = "SELECT status, COUNT(*) as count FROM assistance_applications WHERE application_date BETWEEN ? AND ? GROUP BY status";
    $stmt_subsidy_status = $conn->prepare($sql_subsidy_status);

    if ($stmt_subsidy_status) {
        $stmt_subsidy_status->bind_param("ss", $startDate, $endDate);
        $stmt_subsidy_status->execute();
        $result_subsidy_status = $stmt_subsidy_status->get_result();

        if ($result_subsidy_status) {
            while ($row = $result_subsidy_status->fetch_assoc()) {
                switch ($row['status']) {
                    case 'Approved':
                        // This count holds all approved, which will be hypothetically split below
                        $subsidyApprovedPendingClaim += $row['count']; 
                        break;
                    case 'Pending':
                        $subsidyPendingReview += $row['count'];
                        break;
                    case 'Rejected':
                        $subsidyRejected += $row['count'];
                        break;
                    case 'Claimed':
                        $subsidyApprovedClaimed += $row['count'];
                        break;
                }
            }
        }
        $stmt_subsidy_status->close();
    } else {
        error_log("Error preparing subsidy status statement: " . $conn->error);
    }
    
    // NOTE ON SUBSIDY LOGIC: Keep the existing hypothetical split for consistency if 'Claimed' is not fully tracked:
    $totalApprovedFromDB = $subsidyApprovedPendingClaim; // Approved count
    $subsidyApprovedClaimed += floor($totalApprovedFromDB * 0.7); 
    $subsidyApprovedPendingClaim = $totalApprovedFromDB - floor($totalApprovedFromDB * 0.7); 


    // --- 3. Farmer Age Distribution (Secured with Prepared Statements) ---
    $sql_farmer_ages = "SELECT age FROM farmers WHERE created_at BETWEEN ? AND ?";
    $stmt_farmer_ages = $conn->prepare($sql_farmer_ages);

    if ($stmt_farmer_ages) {
        $stmt_farmer_ages->bind_param("ss", $startDate, $endDate);
        $stmt_farmer_ages->execute();
        $result_farmer_ages = $stmt_farmer_ages->get_result();

        if ($result_farmer_ages) {
            while ($row = $result_farmer_ages->fetch_assoc()) {
                $age = (int)$row['age']; // Ensure age is treated as integer
                if ($age >= 18 && $age <= 25) {
                    $farmerAge18_25++;
                } elseif ($age >= 26 && $age <= 35) {
                    $farmerAge26_35++;
                } elseif ($age >= 36 && $age <= 45) {
                    $farmerAge36_45++;
                } elseif ($age >= 46 && $age <= 55) {
                    $farmerAge46_55++;
                } elseif ($age >= 56 && $age <= 65) {
                    $farmerAge56_65++;
                } elseif ($age > 65) {
                    $farmerAge65_plus++;
                }
            }
        }
        $stmt_farmer_ages->close();
    } else {
        error_log("Error preparing farmer age statement: " . $conn->error);
    }

    // --- NEW: 4. Disaster Impact by Type chart (Secured with Prepared Statements) ---
    // Assuming a table 'disaster_reports' with 'disaster_type' and 'report_date'
    $sql_disaster_impact = "SELECT
                                disaster_type,
                                COUNT(DISTINCT farmer_id) as affected_farmers_count
                            FROM disaster_reports
                            -- Filter out NULL or empty disaster type entries and apply date filter securely
                            WHERE disaster_type IS NOT NULL AND disaster_type != '' AND report_date BETWEEN ? AND ?
                            GROUP BY disaster_type
                            ORDER BY affected_farmers_count DESC
                            LIMIT 6";

    $stmt_disaster = $conn->prepare($sql_disaster_impact);

    if ($stmt_disaster) {
        // NOTE: The end date is included in the period, so we use 'ss' for date strings
        $stmt_disaster->bind_param("ss", $startDate, $endDate); 
        $stmt_disaster->execute();
        $result_disaster_impact = $stmt_disaster->get_result(); // Get the result set

        if ($result_disaster_impact) {
            while ($row = $result_disaster_impact->fetch_assoc()) {
                $disasterTypeLabels[] = htmlspecialchars($row['disaster_type']); // Sanitize disaster name
                $disasterTypeData[] = $row['affected_farmers_count']; 
            }
        }
        $stmt_disaster->close();
    } else {
        error_log("Error preparing disaster impact statement: " . $conn->error);
    }

    // --- 5. Other Key Metrics ---
    
    // Total Active Farmers (No date filter needed)
    $sql_total_active_farmers = "SELECT COUNT(*) as total FROM farmers";
    $result_total_active_farmers = $conn->query($sql_total_active_farmers);
    if ($result_total_active_farmers) {
        $row = $result_total_active_farmers->fetch_assoc();
        $totalActiveFarmers = $row['total'];
    } else {
        error_log("Error fetching total active farmers: " . $conn->error);
    }

    // Total Hectares Planted (Secured with Prepared Statements)
    $sql_total_hectares_planted = "SELECT SUM(hectares) as total_hectares FROM planting_status WHERE status = 'Planted' AND created_at BETWEEN ? AND ?";
    $stmt_hectares = $conn->prepare($sql_total_hectares_planted);

    if ($stmt_hectares) {
        $stmt_hectares->bind_param("ss", $startDate, $endDate);
        $stmt_hectares->execute();
        $result_total_hectares_planted = $stmt_hectares->get_result();

        if ($result_total_hectares_planted) {
            $row = $result_total_hectares_planted->fetch_assoc();
            $totalHectaresPlanted = $row['total_hectares'] ?? 0;
        }
        $stmt_hectares->close();
    } else {
        error_log("Error preparing total hectares planted statement: " . $conn->error);
    }

    // Pending Subsidy Requests (Secured with Prepared Statements)
    $sql_pending_subsidy_requests = "SELECT COUNT(*) as total FROM assistance_applications WHERE status = 'Pending' AND application_date BETWEEN ? AND ?";
    $stmt_pending_subsidy = $conn->prepare($sql_pending_subsidy_requests);

    if ($stmt_pending_subsidy) {
        $stmt_pending_subsidy->bind_param("ss", $startDate, $endDate);
        $stmt_pending_subsidy->execute();
        $result_pending_subsidy_requests = $stmt_pending_subsidy->get_result();

        if ($result_pending_subsidy_requests) {
            $row = $result_pending_subsidy_requests->fetch_assoc();
            $pendingSubsidyRequests = $row['total'];
        }
        $stmt_pending_subsidy->close();
    } else {
        error_log("Error preparing pending subsidy statement: " . $conn->error);
    }

    // Farmers Registered in Period (Secured with Prepared Statements)
    $sql_farmers_registered = "SELECT COUNT(*) as total FROM farmers WHERE created_at BETWEEN ? AND ?";
    $stmt_farmers_registered = $conn->prepare($sql_farmers_registered);

    if ($stmt_farmers_registered) {
        $stmt_farmers_registered->bind_param("ss", $startDate, $endDate);
        $stmt_farmers_registered->execute();
        $result_farmers_registered = $stmt_farmers_registered->get_result();

        if ($result_farmers_registered) {
            $row = $result_farmers_registered->fetch_assoc();
            $farmersRegistered = $row['total'];
        }
        $stmt_farmers_registered->close();
    } else {
        error_log("Error preparing farmers registered statement: " . $conn->error);
    }

    // All Crops List (Secured with Prepared Statements)
    $allCrops = [];
    $sql_all_crops = "SELECT DISTINCT crop FROM farmers WHERE crop IS NOT NULL AND crop != '' ORDER BY crop";
    $stmt_all_crops = $conn->prepare($sql_all_crops);

    if ($stmt_all_crops) {
        $stmt_all_crops->execute();
        $result_all_crops = $stmt_all_crops->get_result();

        if ($result_all_crops) {
            while ($row = $result_all_crops->fetch_assoc()) {
                $allCrops[] = htmlspecialchars($row['crop']);
            }
        }
        $stmt_all_crops->close();
    } else {
        error_log("Error preparing all crops statement: " . $conn->error);
    }
}

if (isset($conn) && $conn) {
    $conn->close(); // Close connection after all data fetching
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Account - Reports & Analytics</title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Fonts -->
    <!-- MODIFIED: Changed font links to match farmer-dashboard.php -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <!-- Chart.js for Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom Styles (COPIED AND ADAPTED FROM farmer-dashboard.php) -->
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
            /* MODIFIED: Explicitly set sidebar font to Be Vietnam Pro for UI/Nav consistency */
            font-family: "Be Vietnam Pro", sans-serif; 
        }
        
        /* Sidebar Menu Label Style */
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

        /* MODIFIED: Header Brand (Logo and Text) */
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
        /* END MODIFIED HEADER BRAND */
        
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

        /* --- Main Content Area (CONSISTENT) --- */
        main {
            margin-left: 250px;
            /* Adjusted padding-top from 72px to match header height */
            padding: 72px 2rem 2rem 2rem; 
            background: #f8f9fa;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        main.collapsed {
            margin-left: 0;
        }
        
        /* 5. Typography Consistency: Headings */
        h1, h2, h3, h4, h5, h6, .card-title, .page-title {
            font-family: "Be Vietnam Pro", sans-serif;
            color: #0f5132; /* Dark Green */
        }
        
        /* NEW: Style for the Page Title */
        .page-title {
            font-size: 1.5rem; 
            font-weight: 600; 
            margin-bottom: 1rem;
        }
        
        /* 2. Button and Alert Unification: Button Theme */
        .btn-theme {
            background-color: #19860f;
            color: #fff;
            border-color: #19860f;
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 15px; /* Added for consistency */
            padding: 10px 20px; /* Added for consistency */
            border-radius: 4px; /* Added for consistency */
            transition: all 0.3s ease; /* Added for consistency */
        }

        .btn-theme:hover {
            background-color: #146c0b;
            border-color: #146c0b;
            color: #fff;
            transform: translateY(-2px); /* Added for consistency */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Added for consistency */
        }

        /* 2. Button and Alert Unification: Outline Button Theme (Adjusted existing for consistency) */
        .btn-outline-success {
            color: #19860f;
            border-color: #19860f;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .btn-outline-success:hover {
            background-color: #19860f;
            color: #fff;
        }
        
        /* Card styles for consistency */
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            border: 1px solid #ddd; /* Added for consistency */
        }

        .card-body h2 {
            font-size: 2rem;
            margin-top: 5px;
            font-weight: 700;
            color: #19860f;
            font-family: "Be Vietnam Pro", sans-serif; /* Explicit font for large numbers */
        }

        .card-body h6 {
            font-size: 14px;
            color: #6c757d;
        }
        
        /* Status badge consistency */
        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.4rem;
            font-size: 13px;
            font-weight: 500;
            font-family: "Be Vietnam Pro", sans-serif;
            display: inline-block;
        }
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
        
        #sidebarToggleBtn {
            color: #0f5132;
        }

        #sidebarToggleBtn:hover {
            color: #146c0b;
        }

        /* Filter controls styling */
        .filter-controls .form-label {
            font-weight: 500;
            color: #333;
        }
        .filter-controls .form-select {
            border-radius: 0.375rem;
        }

        /* Responsive adjustments (Updated to match the farmer-dashboard logic) */
        @media (max-width: 768px) {
            .sidebar {
                width: 250px;
                position: fixed;
                height: 100vh;
                z-index: 1050;
            }

            .sidebar.collapsed {
                left: -250px;
            }

            .card-header-custom {
                left: 0;
                top: 0;
                width: 100%;
                /* Ensuring it remains fixed and full width */
            }

            main {
                margin-left: 0;
                padding-top: 72px;
            }

            main.collapsed {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar (CONSISTENT DESIGN) -->
    <nav class="sidebar">
        <!-- Logo and Text (Consistent with farmer-dashboard.php) -->
        <a href="municipal-dashboard.php" class="header-brand">
            <img src="../photos/logo.png" alt="Department of Agriculture Logo" />
            <div>Agriconnect</div>
        </a>

        <!-- Menu Label (Consistent) -->
        <div class="sidebar-menu-label">Main Menu</div>
        
        <ul class="nav flex-column">
            <!-- Municipal Links (Ensure 'active' is on the correct page) -->
            <li class="nav-item"><a href="municipal-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="municipal-subsidy_management.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i> Subsidy Management</a></li>
            <li class="nav-item"><a href="municipal-qrcode_management.php" class="nav-link"><i class="fas fa-qrcode"></i> QR Code Management</a></li>
            <li class="nav-item"><a href="municipal-crop_monitoring.php" class="nav-link"><i class="fas fa-seedling"></i> Crop Monitoring</a></li>
            <li class="nav-item"><a href="municipal-reports_analytics.php" class="nav-link active"><i class="fas fa-chart-line"></i> Reports & Analytics</a></li>
            <li class="nav-item"><a href="municipal-farmer_profiles.php" class="nav-link"><i class="fas fa-users"></i> Farmer Profiles</a></li>
            <li class="nav-item"><a href="municipal-announcements.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
        </ul>
        
        <!-- Logout Section (Consistent) -->
        <div class="sidebar-logout">
            <a href="municipal-logout.php" class="nav-link">
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
    <div class="container-fluid">
        <!-- Page Title (Uses the new style) -->
        <h1 class="page-title">Reports & Analytics</h1>

        <form method="GET" action="municipal-reports_analytics.php" class="filter-controls row g-3 align-items-end mb-4">
            <div class="col-md-3">
                <label for="reportType" class="form-label">Report Type</label>
                <select class="form-select" id="reportType" name="reportType">
                    <option value="crop" <?php echo $reportType == 'crop' ? 'selected' : ''; ?>>Crop Performance</option>
                    <option value="subsidy" <?php echo $reportType == 'subsidy' ? 'selected' : ''; ?>>Subsidy Distribution</option>
                    <option value="farmer" <?php echo $reportType == 'farmer' ? 'selected' : ''; ?>>Farmer Demographics</option>
                    <option value="disaster" <?php echo $reportType == 'disaster' ? 'selected' : ''; ?>>Disaster Impact</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="periodFilter" class="form-label">Period</label>
                <select class="form-select" id="periodFilter" name="periodFilter">
                    <option value="current" <?php echo $periodFilter == 'current' ? 'selected' : ''; ?>>Current Season</option>
                    <option value="last3m" <?php echo $periodFilter == 'last3m' ? 'selected' : ''; ?>>Last 3 Months</option>
                    <option value="last6m" <?php echo $periodFilter == 'last6m' ? 'selected' : ''; ?>>Last 6 Months</option>
                    <option value="yearly" <?php echo $periodFilter == 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                    <option value="custom" <?php echo $periodFilter == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-theme w-100"><i class="fas fa-filter me-2"></i>Apply Filters</button>
            </div>
        </form>

        <div class="row">
            <?php if ($reportType == 'crop'): ?>
            <!-- Crop Performance Section (Displays when reportType is 'crop') -->
            <div class="col-lg-6">
                <div class="report-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Crop Popularity by Farmer Count</h4>
                        <button class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i> Download</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="cropYieldChart"></canvas>
                    </div>
                    <p class="text-muted mt-3 mb-0" style="font-size: 0.9rem;">
                        The top 6 crops based on the number of registered farmers in the period (<?php echo htmlspecialchars($startDate); ?> to <?php echo htmlspecialchars($endDate); ?>).
                    </p>
                </div>
            </div>

            <!-- Subsidy Distribution Section (Secondary for Crop) -->
            <div class="col-lg-6">
                <div class="report-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Subsidy Distribution Status</h4>
                        <button class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i> Download</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="subsidyStatusChart"></canvas>
                    </div>
                    <p class="text-muted mt-3 mb-0" style="font-size: 0.9rem;">
                        Breakdown of subsidy requests by status (Pending, Approved, Claimed) in the period (<?php echo htmlspecialchars($startDate); ?> to <?php echo htmlspecialchars($endDate); ?>).
                    </p>
                </div>
            </div>

            <?php elseif ($reportType == 'disaster'): ?>
            <!-- NEW: Disaster Impact Section (Displays when reportType is 'disaster') -->
            <div class="col-lg-6">
                <div class="report-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Disaster Impact by Type</h4>
                        <button class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i> Download</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="disasterImpactChart"></canvas>
                    </div>
                    <p class="text-muted mt-3 mb-0" style="font-size: 0.9rem;">
                        Number of unique farmers affected by the top disaster types in the period (<?php echo htmlspecialchars($startDate); ?> to <?php echo htmlspecialchars($endDate); ?>).
                    </p>
                </div>
            </div>

            <!-- Subsidy Distribution Section (Secondary for Disaster) -->
            <div class="col-lg-6">
                <div class="report-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Subsidy Distribution Status</h4>
                        <button class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i> Download</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="subsidyStatusChart"></canvas>
                    </div>
                    <p class="text-muted mt-3 mb-0" style="font-size: 0.9rem;">
                        Breakdown of subsidy requests by status (Pending, Approved, Claimed) in the period (<?php echo htmlspecialchars($startDate); ?> to <?php echo htmlspecialchars($endDate); ?>).
                    </p>
                </div>
            </div>

            <?php else: // Fallback for other report types ?>
                <!-- Fallback/Placeholder: You can add content for 'subsidy' or 'farmer' here -->
                <div class="col-lg-12">
                    <div class="alert alert-info" role="alert">
                        <strong>Report Selected: <?php echo ucfirst(htmlspecialchars($reportType)); ?>.</strong> Displaying general metrics and the Subsidy Distribution chart. Full report customization for this type is not yet implemented.
                    </div>
                </div>
                <!-- Subsidy Distribution Section (Default when no specific report is chosen) -->
                <div class="col-lg-6 offset-lg-3">
                    <div class="report-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4>Subsidy Distribution Status (Default Chart)</h4>
                            <button class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i> Download</button>
                        </div>
                        <div class="chart-container">
                            <canvas id="subsidyStatusChart"></canvas>
                        </div>
                        <p class="text-muted mt-3 mb-0" style="font-size: 0.9rem;">
                            Breakdown of subsidy requests by status (Pending, Approved, Claimed) in the period (<?php echo htmlspecialchars($startDate); ?> to <?php echo htmlspecialchars($endDate); ?>).
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Other Reports and Data Tables -->
            <div class="col-lg-12">
                <div class="report-section">
                    <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
                        <h4>Other Key Metrics</h4>
                        <button class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i> Download All Data</button>
                    </div>
                    <div class="row">
                        <div class="col-md-4"> <!-- FIX: Changed col-md-3 to col-md-4 for better spacing (1/3 of the row) -->
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Total Active Farmers</h6>
                                    <h2 class="card-text text-success"><?php echo number_format($totalActiveFarmers); ?></h2>
                                    <a href="municipal-farmer_profiles.php" class="btn-link">View all farmers</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4"> <!-- FIX: Changed col-md-3 to col-md-4 for better spacing (1/3 of the row) -->
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Farmers Registered (<?php echo $periodFilter == 'current' ? 'Last 6 Months' : ($periodFilter == 'last3m' ? 'Last 3 Months' : ($periodFilter == 'last6m' ? 'Last 6 Months' : ($periodFilter == 'yearly' ? 'Yearly' : 'Custom Period'))); ?>)</h6>
                                    <h2 class="card-text text-info"><?php echo number_format($farmersRegistered); ?></h2>
                                    <a href="municipal-farmer_profiles.php" class="btn-link">View registered farmers</a>
                                </div>
                            </div>
                        </div>
                        <!-- The "Total Hectares Planted" card has been correctly removed. -->
                        <div class="col-md-4"> <!-- FIX: Changed col-md-3 to col-md-4 for better spacing (1/3 of the row) -->
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Pending Subsidy Requests</h6>
                                    <h2 class="card-text text-warning"><?php echo number_format($pendingSubsidyRequests); ?></h2>
                                    <a href="municipal-subsidy_management.php" class="btn-link">Review requests</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Bootstrap Script -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js Initialization (UPDATED LABELS and NEW CHART) -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // PHP variables for crop data
        const cropYieldLabels = <?php echo json_encode($cropYieldLabels); ?>;
        const cropYieldData = <?php echo json_encode($cropYieldData); ?>;

        // PHP variables for subsidy data
        const subsidyApprovedClaimed = <?php echo json_encode($subsidyApprovedClaimed); ?>;
        const subsidyApprovedPendingClaim = <?php echo json_encode($subsidyApprovedPendingClaim); ?>;
        const subsidyPendingReview = <?php echo json_encode($subsidyPendingReview); ?>;
        const subsidyRejected = <?php echo json_encode($subsidyRejected); ?>;

        // NEW: PHP variables for disaster data
        const disasterTypeLabels = <?php echo json_encode($disasterTypeLabels); ?>;
        const disasterTypeData = <?php echo json_encode($disasterTypeData); ?>;

        // Crop Performance Data (Bar Chart) - LABELS UPDATED
        const cropPerformanceData = {
            labels: cropYieldLabels,
            datasets: [{
                // **UPDATED LABEL**
                label: 'Number of Farmers',
                data: cropYieldData,
                backgroundColor: [
                    'rgba(25, 134, 15, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)'
                ],
                borderColor: [
                    'rgba(25, 134, 15, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)'
                ],
                borderWidth: 1
            }]
        };
        
        // NEW: Disaster Impact Data (Bar Chart)
        const disasterImpactData = {
            labels: disasterTypeLabels,
            datasets: [{
                label: 'Affected Farmers Count',
                data: disasterTypeData,
                backgroundColor: [
                    'rgba(220, 53, 69, 0.7)', // Red/Danger for disaster
                    'rgba(255, 193, 7, 0.7)', // Warning
                    'rgba(23, 162, 184, 0.7)', // Info
                    'rgba(108, 117, 125, 0.7)', // Secondary
                    'rgba(25, 134, 15, 0.7)', // Success
                    'rgba(153, 102, 255, 0.7)' // Purple
                ],
                borderColor: [
                    'rgba(220, 53, 69, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(23, 162, 184, 1)',
                    'rgba(108, 117, 125, 1)',
                    'rgba(25, 134, 15, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        };

        // Subsidy Status Data (Doughnut Chart)
        const subsidyStatusData = {
            labels: ['Approved & Claimed', 'Approved (Pending Claim)', 'Pending Review', 'Rejected'],
            datasets: [{
                label: '# of Subsidies',
                data: [subsidyApprovedClaimed, subsidyApprovedPendingClaim, subsidyPendingReview, subsidyRejected],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.7)', /* Green for Claimed */
                    'rgba(255, 193, 7, 0.7)', /* Yellow for Approved Pending */
                    'rgba(23, 162, 184, 0.7)', /* Blue for Pending Review */
                    'rgba(220, 53, 69, 0.7)' /* Red for Rejected */
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(23, 162, 184, 1)',
                    'rgba(220, 53, 69, 1)'
                ],
                borderWidth: 1
            }]
        };

        // Chart options configuration
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: false,
                },
                legend: {
                    display: false
                }
            }
        };
        
        // --- CHART INITIALIZATION ---
        
        // Crop Yield Chart (Only initialize if the canvas exists)
        const cropYieldCtx = document.getElementById('cropYieldChart');
        if (cropYieldCtx) {
            new Chart(cropYieldCtx.getContext('2d'), {
                type: 'bar',
                data: cropPerformanceData, // Use the fetched data
                options: {
                    ...chartOptions,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                // **UPDATED Y-AXIS TITLE**
                                text: 'Number of Farmers'
                            }
                        }
                    }
                }
            });
        }
        
        // NEW: Disaster Impact Chart (Only initialize if the canvas exists)
        const disasterImpactCtx = document.getElementById('disasterImpactChart');
        if (disasterImpactCtx) {
            new Chart(disasterImpactCtx.getContext('2d'), {
                type: 'bar',
                data: disasterImpactData,
                options: {
                    ...chartOptions,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Affected Farmers'
                            }
                        }
                    }
                }
            });
        }


        // Subsidy Status Chart (Doughnut)
        const subsidyStatusCtx = document.getElementById('subsidyStatusChart');
        if(subsidyStatusCtx) {
            new Chart(subsidyStatusCtx.getContext('2d'), {
                type: 'doughnut',
                data: subsidyStatusData,
                options: chartOptions // Use shared options for simplicity
            });
        }
    });
</script>

<!-- JavaScript for Sidebar Toggle (COPIED FROM farmer-dashboard.php) -->
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