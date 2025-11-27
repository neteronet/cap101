<?php
error_reporting(E_ALL); // Report all errors
ini_set('display_errors', 1); // Display errors for debugging

session_start();

include '../includes/connection.php'; // Ensure this path is correct

// Redirect if user_id is not set or not an integer
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: municipal-login.php");
    exit();
}
if ($_SESSION['user_type'] != 'mao') {
    header("location: admin-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Mao'; // Default fallback

// --- IMPROVED NAME FETCHING ---
// Always try to fetch the name from the database for accuracy.
// This ensures that if the session name is outdated or not set, the DB name is used.
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

// --- Fetch Farmers Count ---
$farmersCount = 0; // Initialize with 0
$countFarmersStmt = $conn->prepare("SELECT COUNT(farmer_id) AS total_farmers FROM farmers");
if ($countFarmersStmt === false) {
    error_log("SQL Error for Farmers Count: " . $conn->error);
} else {
    $countFarmersStmt->execute();
    $countFarmersStmt->bind_result($totalFarmers);
    $countFarmersStmt->fetch();
    if ($totalFarmers !== null) {
        $farmersCount = $totalFarmers;
    }
    $countFarmersStmt->close();
}

// --- Fetch Total Farms Count (from crop_monitoring table) ---
$farmsCount = 0; // Initialize with 0
$countFarmsStmt = $conn->prepare("SELECT COUNT(id) AS total_farms FROM planting_status");
if ($countFarmsStmt === false) {
    error_log("SQL Error for Farms Count: " . $conn->error);
} else {
    $countFarmsStmt->execute();
    $countFarmsStmt->bind_result($totalFarms);
    $countFarmsStmt->fetch();
    if ($totalFarms !== null) {
        $farmsCount = $totalFarms;
    }
    $countFarmsStmt->close();
}

// --- Fetch Total Subsidy Requests ---
$subsidyRequestsCount = 0; // Initialize
$countSubsidyRequestsStmt = $conn->prepare("SELECT COUNT(application_id) AS total_requests FROM assistance_applications");
if ($countSubsidyRequestsStmt === false) {
    error_log("SQL Error for Subsidy Requests Count: " . $conn->error);
} else {
    $countSubsidyRequestsStmt->execute();
    $countSubsidyRequestsStmt->bind_result($totalSubsidyRequests);
    $countSubsidyRequestsStmt->fetch();
    if ($totalSubsidyRequests !== null) {
        $subsidyRequestsCount = $totalSubsidyRequests;
    }
    $countSubsidyRequestsStmt->close();
}

// --- Fetch Pending Verifications (Subsidy Requests with status 'Pending') ---
$pendingVerificationsCount = 0; // Initialize
$countPendingVerificationsStmt = $conn->prepare("SELECT COUNT(application_id) AS total_pending FROM assistance_applications WHERE status = 'Pending'");
if ($countPendingVerificationsStmt === false) {
    error_log("SQL Error for Pending Verifications Count: " . $conn->error);
} else {
    $countPendingVerificationsStmt->execute();
    $countPendingVerificationsStmt->bind_result($totalPendingVerifications);
    $countPendingVerificationsStmt->fetch();
    if ($totalPendingVerifications !== null) {
        $pendingVerificationsCount = $totalPendingVerifications;
    }
    $countPendingVerificationsStmt->close();
}

// --- Fetch Crop Monitoring Summary ---
$cropSummary = []; // Initialize as an empty array
$fetchCropSummaryStmt = $conn->prepare("SELECT status, crop_type, COUNT(id) AS count FROM planting_status GROUP BY status, crop_type ORDER BY status, crop_type");
if ($fetchCropSummaryStmt === false) {
    error_log("SQL Error for Crop Monitoring Summary: " . $conn->error);
} else {
    $fetchCropSummaryStmt->execute();
    $result = $fetchCropSummaryStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $status = $row['status'];
        $cropType = $row['crop_type'];
        $count = $row['count'];

        if (!isset($cropSummary[$status])) {
            $cropSummary[$status] = [];
        }
        $cropSummary[$status][$cropType] = $count;
    }
    $fetchCropSummaryStmt->close();
}
// --- End Fetch Crop Monitoring Summary ---


// --- Fetch Recent Subsidy Activity (Latest 5, combining approved/rejected and pending for display) ---
$recentSubsidyActivity = [];
$fetchRecentSubsidyStmt = $conn->prepare("
    SELECT
        aa.status,
        aa.assistance_type,
        aa.seed_type,
        aa.seed_quantity,
        aa.engine_type,
        f.name AS farmer_name,
        aa.qr_code_data
    FROM assistance_applications aa
    JOIN farmers f ON aa.farmer_user_id = f.farmer_id
    ORDER BY aa.application_date DESC
    LIMIT 5
");
if ($fetchRecentSubsidyStmt === false) {
    error_log("SQL Error for Recent Subsidy Activity: " . $conn->error);
} else {
    $fetchRecentSubsidyStmt->execute();
    $result = $fetchRecentSubsidyStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentSubsidyActivity[] = $row;
    }
    $fetchRecentSubsidyStmt->close();
}


$conn->close(); // Close the database connection here, after all queries.

// --- HELPER FUNCTION: Status to CSS Class Mapping (from farmer-dashboard.php) ---
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Account - Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Custom Styles (Consistent Design) -->
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

        /* --- Fixed Top Header --- */
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

        /* Color Palette Standardization: Links */
        a {
            color: #19860f;
        }

        a:hover {
            color: #146c0b;
        }

        /* Typography Consistency: Headings and Titles */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .card-title,
        .modal-title,
        .page-title { 
            font-family: "Be Vietnam Pro", sans-serif;
            color: #0f5132;
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
        
        /* Explicit Standard Card Text Size for Consistency (~15px) */
        .card-text, 
        .card-body p:not(.card-title):not(.dashboard-description), 
        .list-unstyled li {
            font-size: 0.9375rem; 
        }
        .card-text.small, 
        .list-unstyled.small li, 
        .card-text.text-muted.small {
            font-size: 0.875rem !important; 
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

        /* Card Style */
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #ddd;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.4rem;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
            font-family: "Be Vietnam Pro", sans-serif;
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

        /* Text colors */
        .text-info { color: #0dcaf0 !important; }
        .text-success { color: #198754 !important; }
        .text-warning { color: #ffc107 !important; }
        .text-danger { color: #dc3545 !important; }
        .text-muted { color: #6c757d !important; }
        
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
        <!-- Logo and Text (Consistent with farmer-dashboard structure) -->
        <a href="ProvincialAgriHome.html" class="header-brand">
            <img src="../photos/logo.png" />
            <div>Agriconnect</div>
        </a>

        <!-- Menu Label (Consistent) -->
        <div class="sidebar-menu-label">Main Menu</div>

        <ul class="nav flex-column">
            <li class="nav-item"><a href="municipal-dashboard.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="municipal-subsidy_management.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i> Subsidy Management</a></li>
            <li class="nav-item"><a href="municipal-qrcode_management.php" class="nav-link"><i class="fas fa-qrcode"></i> QR Code Management</a></li>
            <li class="nav-item"><a href="municipal-crop_monitoring.php" class="nav-link"><i class="fas fa-seedling"></i> Crop Monitoring</a></li>
            <li class="nav-item"><a href="municipal-reports_analytics.php" class="nav-link"><i class="fas fa-chart-line"></i> Reports & Analytics</a></li>
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
            <!-- UPDATED CLASS -->
            <h1 class="page-title">Dashboard</h1>
            <!-- UPDATED CLASS -->
            <p class="text-muted mb-4 dashboard-description">Quick summary of system activity in your municipality.</p>

            <div class="row g-4">
                <!-- Farmers Registered -->
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h6 class="text-muted">Farmers Registered</h6>
                            <h2 class="fw-bold" id="farmersCount"><?php echo $farmersCount; ?></h2>
                            <a href="municipal-farmer_profiles.php" class="btn-link mt-auto">View all farmers <i class="fas fa-arrow-right fa-xs ms-1"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Total Farms -->
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h6 class="text-muted">Total Farms</h6>
                            <h2 class="fw-bold" id="farmsCount"><?php echo $farmsCount; ?></h2>
                            <a href="municipal-crop_monitoring.php" class="btn-link mt-auto">View farm details <i class="fas fa-arrow-right fa-xs ms-1"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Subsidy Requests -->
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h6 class="text-muted">Subsidy Requests</h6>
                            <h2 class="fw-bold" id="subsidyCount"><?php echo $subsidyRequestsCount; ?></h2>
                            <a href="municipal-subsidy_management.php" class="btn-link mt-auto">Manage requests <i class="fas fa-arrow-right fa-xs ms-1"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Pending Verifications -->
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h6 class="text-muted">Pending Verifications</h6>
                            <h2 class="fw-bold text-warning" id="pendingCount"><?php echo $pendingVerificationsCount; ?></h2>
                            <a href="municipal-subsidy_management.php" class="btn-link mt-auto">Review verifications <i class="fas fa-arrow-right fa-xs ms-1"></i></a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-3">
                <!-- Crop Monitoring Summary -->
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-chart-bar me-2"></i>Crop Monitoring Summary</h5>
                            <p class="card-text text-muted small">Overview of current crop statuses and planting updates.</p>
                            <ul class="list-unstyled small mb-3">
                                <?php
                                $hasCropSummary = false;
                                foreach ($cropSummary as $status => $cropTypes) {
                                    if (!empty($cropTypes)) {
                                        $hasCropSummary = true;
                                        $iconClass = '';
                                        $textColor = '';
                                        switch ($status) {
                                            case 'Planted':
                                                $iconClass = 'fa-check-circle';
                                                $textColor = 'text-success';
                                                break;
                                            case 'Not Planted':
                                                $iconClass = 'fa-times-circle';
                                                $textColor = 'text-danger';
                                                break;
                                            case 'Growing':
                                                $iconClass = 'fa-leaf';
                                                $textColor = 'text-info';
                                                break;
                                            case 'Harvested':
                                                $iconClass = 'fa-tractor';
                                                $textColor = 'text-primary';
                                                break;
                                            case 'Pending Update':
                                                $iconClass = 'fa-hourglass-half';
                                                $textColor = 'text-warning';
                                                break;
                                            default:
                                                $iconClass = 'fa-info-circle';
                                                $textColor = 'text-muted';
                                                break;
                                        }
                                        foreach ($cropTypes as $cropType => $count) {
                                            echo '<li><i class="fas ' . $iconClass . ' ' . $textColor . ' me-2"></i>' . htmlspecialchars($cropType) . ' (' . htmlspecialchars($status) . '): ' . $count . ' farms</li>';
                                        }
                                    }
                                }
                                if (!$hasCropSummary) {
                                    echo '<li>No crop monitoring data available.</li>';
                                }
                                ?>
                            </ul>
                            <a href="municipal-crop_monitoring.php" class="btn btn-theme mt-auto">View Crop Monitoring <i class="fas fa-arrow-right fa-xs ms-1"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Latest Subsidy Approvals -->
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-hand-holding-usd me-2"></i>Recent Subsidy Activity</h5>
                            <p class="card-text text-muted small">Latest approvals, rejections, and pending requests.</p>
                            <ul class="list-unstyled small mb-3">
                                <?php if (empty($recentSubsidyActivity)): ?>
                                    <li>No recent subsidy activity.</li>
                                <?php else: ?>
                                    <?php foreach ($recentSubsidyActivity as $activity): ?>
                                        <?php
                                        $icon = '';
                                        $text_color = '';
                                        $description = '';
                                        $status_class = get_status_class($activity['status']); // Use the helper function

                                        if ($activity['status'] == 'Approved') {
                                            $icon = 'fa-check-double';
                                            $text_color = 'text-success';
                                        } elseif ($activity['status'] == 'Rejected') {
                                            $icon = 'fa-users-slash';
                                            $text_color = 'text-danger';
                                        } else { // Pending
                                            $icon = 'fa-hourglass-half';
                                            $text_color = 'text-warning';
                                        }
                                        
                                        $description = htmlspecialchars($activity['assistance_type']) . " request";
                                        if (!empty($activity['farmer_name'])) {
                                            $description .= " from " . htmlspecialchars($activity['farmer_name']);
                                        }
                                        
                                        // Specific details for display
                                        $details = '';
                                        if ($activity['assistance_type'] == 'Seeds' && !empty($activity['seed_type'])) {
                                            $details .= " (" . htmlspecialchars($activity['seed_type']) . ")";
                                        } elseif ($activity['assistance_type'] == 'Equipment' && !empty($activity['engine_type'])) {
                                            $details .= " (" . htmlspecialchars($activity['engine_type']) . ")";
                                        }
                                        if (!empty($activity['qr_code_data']) && $activity['status'] == 'Approved') {
                                            $details .= " (QR Issued)";
                                        }
                                        ?>
                                        <li>
                                            <i class="fas <?php echo $icon; ?> <?php echo $text_color; ?> me-2"></i>
                                            <?php echo $description; ?> 
                                            <span class="status-badge <?php echo $status_class; ?> ms-1"><?php echo htmlspecialchars($activity['status']); ?></span>
                                            <?php echo $details; ?>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                            <a href="municipal-subsidy_management.php" class="btn btn-theme mt-auto">Manage Subsidies <i class="fas fa-arrow-right fa-xs ms-1"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript for Sidebar Toggle (CONSISTENT FUNCTIONALITY) -->
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