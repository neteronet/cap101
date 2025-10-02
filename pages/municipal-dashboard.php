<?php
session_start(); // Start the session at the very beginning of the script

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("location: municipal-login.php");
    exit();
}
 
// Retrieve the user's name from the session.
$display_name = $_SESSION['name'] ?? 'Mao'; // Fallback to 'Farmer' if not set

$servername = "localhost";
$db_username = "root"; // Your database username
$db_password = "";     // Your database password
$dbname = "cap101"; // Your database name

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    // You might want to redirect to an error page or show a friendly message
    // For now, we'll just exit to prevent further errors
    exit("Database connection failed. Please try again later.");
} else {
    // Fetch user's name from DB for display
    $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
    if ($stmt === false) {
        error_log("SQL Error for fetching user name: " . $conn->error);
    } else {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($fetched_db_name);
        $stmt->fetch();
        if ($fetched_db_name) {
            $display_name = $fetched_db_name; // Use the name fetched from DB
        }
        $stmt->close();
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


    $conn->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Account - Dashboard</title>
<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

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
        border: none;
        transition: all 0.3s ease;
    }

    .btn-theme:hover {
        background-color: #146c0b;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    main {
        margin-left: 250px;
        padding: 1rem 2rem 2rem 2rem;
        padding-top: 72px;
        background: #f8f9fa;
        min-height: 100vh;
    }

    .container-fluid {
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

    .card-body h6 {
        font-size: 14px;
        color: #6c757d;
    }

    .card-body h2 {
        font-size: 2rem;
        margin-top: 5px;
        font-weight: 700;
        color: #19860f;
    }

    .card-body h2.text-warning {
        color: #ffc107 !important;
    }

    .card-body .btn-link {
        font-size: 14px;
        color: #19860f;
        text-decoration: none;
        padding: 0;
    }

    .card-body .btn-link:hover {
        text-decoration: underline;
    }

    .status-badge {
        padding: 0.3em 0.6em;
        border-radius: 0.4rem;
        font-size: 13px;
        font-weight: 500;
    }

    .status-pending {
        background-color: #ffc107;
        color: #856404;
    }

    .status-approved {
        background-color: #28a745;
        color: #fff;
    }

    .status-rejected {
        background-color: #dc3545;
        color: #fff;
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
            <a href="municipal-dashboard.php" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="municipal-farmer_profiles.php" class="nav-link">
                <i class="fas fa-users"></i> Farmer Profiles
            </a>
        </li>
        <li class="nav-item">
            <a href="municipal-crop_monitoring.php" class="nav-link">
                <i class="fas fa-seedling"></i> Crop Monitoring
            </a>
        </li>
        <li class="nav-item">
            <a href="municipal-subsidy_management.php" class="nav-link">
                <i class="fas fa-hand-holding-usd"></i> Subsidy Management
            </a>
        </li>
        <li class="nav-item">
            <a href="municipal-announcements.php" class="nav-link">
                <i class="fas fa-bullhorn"></i> Announcements
            </a>
        </li>
        <li class="nav-item">
            <a href="municipal-reports_analytics.php" class="nav-link">
                <i class="fas fa-chart-line"></i> Reports & Analytics
            </a>
        </li>
        <li class="nav-item">
            <a href="municipal-qrcode_management.php" class="nav-link">
                <i class="fas fa-qrcode"></i> QR Code Management
            </a>
        </li>
    </ul>
</nav>

<!-- Header -->
<div class="card-header card-header-custom d-flex justify-content-end align-items-center">
    <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
    <button class="logout-btn" onclick="location.href='municipal-logout.php'">
        <i class="fas fa-sign-out-alt me-1"></i> Logout
    </button>
</div>

<!-- Main Content -->
<main>
    <div class="container-fluid">
        <h1 class="page-title">Dashboard</h1>
        <p class="text-muted mb-4">Quick summary of system activity in your municipality.</p>

        <div class="row g-4">
            <!-- Farmers Registered -->
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h6 class="text-muted">Farmers Registered</h6>
                        <h2 class="fw-bold" id="farmersCount"><?php echo $farmersCount; ?></h2>
                        <a href="municipal-farmer_profiles.php" class="btn-link mt-auto">View all farmers <i class="fas fa-arrow-right fa-xs ms-1"></i></a>
                    </div>
                </div>
            </div>

            <!-- Total Farms -->
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h6 class="text-muted">Total Farms</h6>
                        <h2 class="fw-bold" id="farmsCount"><?php echo $farmsCount; ?></h2>
                        <a href="municipal-crop_monitoring.php" class="btn-link mt-auto">View farm details <i class="fas fa-arrow-right fa-xs ms-1"></i></a>
                    </div>
                </div>
            </div>

            <!-- Subsidy Requests -->
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h6 class="text-muted">Subsidy Requests</h6>
                        <h2 class="fw-bold" id="subsidyCount"><?php echo $subsidyRequestsCount; ?></h2>
                        <a href="municipal-subsidy_management.php" class="btn-link mt-auto">Manage requests <i class="fas fa-arrow-right fa-xs ms-1"></i></a>
                    </div>
                </div>
            </div>

            <!-- Pending Verifications -->
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
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
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title text-success"><i class="fas fa-chart-bar me-2"></i>Crop Monitoring Summary</h5>
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
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title text-primary"><i class="fas fa-hand-holding-usd me-2"></i>Recent Subsidy Activity</h5>
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

                                        if ($activity['status'] == 'Approved') {
                                            $icon = 'fa-check-double';
                                            $text_color = 'text-success';
                                            $description = "Approved: " . htmlspecialchars($activity['assistance_type']);
                                            if (!empty($activity['farmer_name'])) {
                                                $description .= " for " . htmlspecialchars($activity['farmer_name']);
                                            }
                                            if ($activity['assistance_type'] == 'Seeds' && !empty($activity['seed_type'])) {
                                                $description .= " (" . htmlspecialchars($activity['seed_type']) . ")";
                                            } elseif ($activity['assistance_type'] == 'Equipment' && !empty($activity['engine_type'])) {
                                                $description .= " (" . htmlspecialchars($activity['engine_type']) . ")";
                                            }
                                            if (!empty($activity['qr_code_data'])) {
                                                $description .= " (QR Issued)";
                                            }
                                        } elseif ($activity['status'] == 'Rejected') {
                                            $icon = 'fa-users-slash';
                                            $text_color = 'text-danger';
                                            $description = "Rejected: " . htmlspecialchars($activity['assistance_type']);
                                            if (!empty($activity['farmer_name'])) {
                                                $description .= " for " . htmlspecialchars($activity['farmer_name']);
                                            }
                                        } else { // Pending
                                            $icon = 'fa-hourglass-half';
                                            $text_color = 'text-warning';
                                            $description = "Pending: " . htmlspecialchars($activity['assistance_type']) . " request";
                                            if (!empty($activity['farmer_name'])) {
                                                $description .= " from " . htmlspecialchars($activity['farmer_name']);
                                            }
                                        }
                                    ?>
                                    <li><i class="fas <?php echo $icon; ?> <?php echo $text_color; ?> me-2"></i><?php echo $description; ?></li>
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
</body>
</html>