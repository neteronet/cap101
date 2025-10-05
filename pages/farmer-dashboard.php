<?php
session_start(); // Start the session at the very beginning of the script

include '../includes/connection.php'; // Ensure your connection file is correctly included

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: farmers-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Farmer'; // Default fallback

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
$stmt_crop_status = $conn->prepare("SELECT crop_name, status, update_date FROM farmer_crops WHERE user_id = ? ORDER BY update_date DESC LIMIT 1");
if ($stmt_crop_status) {
    $stmt_crop_status->bind_param("i", $user_id); // Assuming user_id is an integer
    $stmt_crop_status->execute();
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

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
        }

        .btn-theme:hover {
            background-color: #146c0b;
        }

        main {
            margin-left: 250px;
            padding: 1rem 2rem 2rem 2rem;
            padding-top: 72px;
            background: #f8f9fa;
            min-height: 100vh;
        }

        .container {
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

        .card-title {
            color: #19860f;
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
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
                <a href="farmer-dashboard.php" class="nav-link active">
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
                <a href="farmer-planting_status.php" class="nav-link">
                    <i class="fas fa-leaf"></i> Planting Status
                </a>
            </li>
            <li class="nav-item">
                <a href="farmer-progress_tracking.php" class="nav-link">
                    <i class="fas fa-chart-line"></i> Progress Tracking
                </a>
            </li>
            <li class="nav-item">
                <a href="farmer-history_log.php" class="nav-link">
                    <i class="fas fa-chart-line"></i> History Log
                </a>
            </li>
        </ul>
    </nav>

    <!-- Header -->
    <div class="card-header card-header-custom d-flex justify-content-end align-items-center">
        <!-- Changed "username" to "name" in the greeting -->
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <button class="logout-btn" onclick="location.href='farmers-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">

            <h1 class="page-title">Dashboard</h1>
            <p class="text-muted mb-4">
                Here's a quick overview of your activities and important updates.
            </p>

            <div class="row">
                <!-- Announcements Card -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
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

                <!-- Subsidy Status Card -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-hand-holding-usd me-2"></i>Subsidy Status</h5>
                            <p class="card-text text-muted small">
                                Check the status of your assistance applications and claim history.
                            </p>
                            <div class="mb-3">
                                <!-- These are static examples, you'd fetch real data from your DB -->
                                <p class="mb-1">Fertilizer Grant 2024: <span class="status-badge status-approved">Approved</span></p>
                                <p class="mb-1">Seed Distribution Q2: <span class="status-badge status-pending">Pending Review</span></p>
                            </div>
                            <a href="farmer-subsidy_status.php" class="btn btn-theme mt-auto">Go to Subsidy Status</a>
                        </div>
                    </div>
                </div>

                <!-- Crop Monitoring Card -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
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

                <!-- Apply for Assistance Card -->
                <div class="col-md-12 col-lg-12 mt-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-file-invoice me-2"></i>Apply for New Assistance</h5>
                            <p class="card-text text-muted small">
                                Request support for seeds, fertilizer, fuel, and other farming needs. Browse available programs.
                            </p>
                            <p class="card-text">
                                <span class="fw-bold">Available Programs:</span> Seed Subsidy Program, Agricultural Loan Assistance, Farm Equipment Grant.
                            </p>
                            <a href="farmer-apply_for_assistance.php" class="btn btn-theme mt-auto">Start New Application</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>