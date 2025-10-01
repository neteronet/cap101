<?php
session_start(); // Start the session at the very beginning of the script

// Check if the user is logged in. If not, redirect to the login page.
// Adjust 'farmers-login.php' to your actual login page filename and path if different.
if (!isset($_SESSION['user_id'])) {
    header("location: farmers-login.php");
    exit();
}

// Retrieve the user's name from the session.
// In your login example, you stored 'username' (e.g., 'delacruzjuan') in the session.
// We'll use this for the display.
$display_name = $_SESSION['name'] ?? 'Farmer'; // Fallback to 'Farmer' if not set

// If you had a 'full_name' column in your database and stored it in the session,
// you would use that instead. For example, if you stored $_SESSION['full_name']
// $display_name = $_SESSION['full_name'] ?? 'Farmer';

$servername = "localhost";
$db_username = "root"; // Your database username
$db_password = "";     // Your database password
$dbname = "cap101"; // Your database name

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    // Log error or display a generic message, but don't expose database details
    error_log("Database connection failed: " . $conn->connect_error);
    // You might want to redirect to an error page or show a friendly message
} else {
    // Assuming your 'users' table has a 'username' column that serves as the display name
    // If you have a 'first_name' and 'last_name', you'd fetch those.
    $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($fetched_db_name);
    $stmt->fetch();
    if ($fetched_db_name) {
        $display_name = $fetched_db_name; // Use the name fetched from DB
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
    <title>Farmer Account - Subsidy Status</title>

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
            font-size: 16px; /* Matched dashboard */
            line-height: 1.6; /* Matched dashboard */
            color: #333; /* Matched dashboard */
            margin: 0;
        }

        /* Sidebar - Adjusted to match dashboard */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: #19860f;
            padding: 1rem 0;
            overflow-y: auto;
            font-size: 14px; /* Matched dashboard */
            z-index: 1050;
            border-right: 1px solid #ddd;
        }

        .sidebar .nav-link {
            color: #fff;
            padding: 0.6rem 1rem; /* Matched dashboard */
            width: 100%;
            box-sizing: border-box;
            border-radius: 0;
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .sidebar .nav-link i {
            margin-right: 8px; /* Matched dashboard */
            font-size: 1rem; /* Matched dashboard */
        }

        .sidebar .nav-link.active {
            background-color: #fff;
            color: #19860f;
            font-weight: 600;
        }

        .sidebar .nav-link:hover:not(.active) {
            background-color: #146c0b; /* Matched dashboard */
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
            max-width: 120px; /* Matched dashboard */
            height: auto;
            background: #19860f;
            padding: 5px;
            border-radius: 4px;
        }

        .sidebar .header-brand div {
            font-size: 14px; /* Matched dashboard */
            font-weight: 600;
            color: #fff;
            text-align: center;
            margin-top: 6px;
        }

        /* Header (Top Nav) - Adjusted to match dashboard */
        .card-header-custom {
            position: fixed;
            top: 0;
            left: 250px; /* Aligned with sidebar */
            right: 0;
            height: 56px; /* Matched dashboard */
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

        .card-header-custom .header-brand span { /* This selector was for the dashboard's internal header-brand */
            font-size: 1rem;
            font-weight: 600;
            color: #19860f;
        }

        /* Logout Button - Matched dashboard */
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

        /* Themed Buttons - Matched dashboard */
        .btn-theme {
            background-color: #19860f;
            color: #fff;
            font-size: 15px; /* Matched dashboard */
            padding: 10px 20px; /* Matched dashboard */
            border-radius: 4px; /* Matched dashboard */
            transition: all 0.2s ease;
        }

        .btn-theme:hover {
            background-color: #146c0b; /* Matched dashboard */
            color: #fff;
            transform: translateY(-1px); /* Added for consistency */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Added for consistency */
        }

        /* Main Content Area - Adjusted to match dashboard */
        main {
            margin-left: 250px; /* Match sidebar width */
            padding: 1rem 2rem 2rem 2rem; /* Matched dashboard */
            padding-top: 72px; /* To account for fixed header height + some spacing */
            background: #f8f9fa;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px; /* Matched dashboard */
        }

        /* Page Title - Matched dashboard */
        .page-title {
            font-size: 1.8rem; /* Matched dashboard */
            font-weight: 600; /* Matched dashboard */
            color: #19860f; /* Matched dashboard */
            margin-bottom: 1.5rem; /* Adjusted for consistency */
            display: flex;
            align-items: center;
        }

        .page-title i {
            margin-right: 10px;
        }

        /* Card Styling - Matched dashboard */
        .card {
            border-radius: 0.5rem; /* Matched dashboard */
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05); /* Matched dashboard */
            margin-bottom: 1.5rem; /* Adjusted for consistency */
        }

        .card-title {
            color: #19860f; /* Matched dashboard */
            font-size: 1.25rem; /* Matched dashboard */
            font-weight: 600; /* Added for consistency with dashboard */
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
        }

        .card-title i {
            margin-right: 8px;
        }

        /* Status Badges - Matched dashboard */
        .status-badge {
            padding: 0.3em 0.6em; /* Matched dashboard */
            border-radius: 0.4rem; /* Matched dashboard */
            font-size: 13px; /* Matched dashboard */
            font-weight: 500; /* Matched dashboard */
            display: inline-flex;
            align-items: center;
        }

        .status-badge i {
            margin-right: 5px;
        }

        .status-pending {
            background-color: #ffc107;
            color: #856404;
        }

        .status-approved {
            background-color: #28a745;
            color: #fff;
        }

        .status-claimed {
            background-color: #0d6efd; /* Bootstrap primary blue for claimed */
            color: #fff;
        }
        .status-eligible {
            background-color: #28a745; /* Green for eligible */
            color: #fff;
        }
        .status-rejected { /* Added from dashboard for consistency */
            background-color: #dc3545;
            color: #fff;
        }

        /* QR Code Styling */
        .qr-code {
            text-align: center;
            margin-top: 1rem;
        }

        .qr-code img {
            width: 180px;
            height: 180px;
            border: 5px solid #19860f;
            border-radius: 8px; /* Slightly less rounded than original, more consistent with dashboard card borders */
            padding: 8px;
            background: #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        /* Table Styling - Matched dashboard's general feel */
        .table thead th {
            background-color: #e9f5ee; /* Light green for table header */
            color: #19860f;
            font-weight: 600;
            font-size: 0.95rem; /* Adjusted for consistency */
            padding: 0.75rem 1rem; /* Adjusted for consistency */
        }

        .table tbody tr td {
            font-size: 0.95rem; /* Adjusted for consistency */
            padding: 0.75rem 1rem; /* Adjusted for consistency */
        }

        .table tbody tr:nth-child(even) {
            background-color: #f3fcf5; /* Slightly lighter green for even rows */
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
                <a href="farmer-subsidy_status.php" class="nav-link active">
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
        </ul>
    </nav>

    <!-- Header (Top Nav) -->
    <div class="card-header card-header-custom d-flex justify-content-end align-items-center">
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <button class="logout-btn" onclick="location.href='farmers-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container"> <!-- Added container for max-width and centering -->
            <h2 class="page-title">Subsidy Status</h2>
            <p class="text-muted mb-4">
                View your eligibility, approved assistance, and claim history.
            </p>

            <div class="row">
                <!-- Eligibility Status -->
                <div class="col-md-12 mb-4">
                    <div class="card p-4 h-100">
                        <h5 class="card-title"><i class="fas fa-circle-check"></i> Eligibility Status</h5>
                        <p class="card-text fs-5">
                            You are <span class="status-badge status-eligible"><i class="fas fa-check-circle"></i> Eligible</span> for the subsidy program.
                        </p>
                        <p class="text-muted small mt-2">This status is based on your latest profile and application details.</p>
                    </div>
                </div>

                <!-- Claim History -->
                <div class="col-12 mb-4">
                    <div class="card p-4">
                        <h5 class="card-title"><i class="fas fa-history"></i> Claim History</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-borderless">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Assistance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>July 15, 2025</td>
                                        <td>₱5,000 Cash Aid</td>
                                        <td><span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span></td>
                                    </tr>
                                    <tr>
                                        <td>June 10, 2025</td>
                                        <td>10kg Fertilizer</td>
                                        <td><span class="status-badge status-claimed"><i class="fas fa-handshake"></i> Claimed</span></td>
                                    </tr>
                                    <tr>
                                        <td>May 01, 2025</td>
                                        <td>Seed Distribution (Rice)</td>
                                        <td><span class="status-badge status-claimed"><i class="fas fa-handshake"></i> Claimed</span></td>
                                    </tr>
                                    <tr>
                                        <td>March 20, 2025</td>
                                        <td>Fuel Subsidy</td>
                                        <td><span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Rejected</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- QR Code for Claiming -->
                <div class="col-12">
                    <div class="card p-4 text-center">
                        <h5 class="card-title justify-content-center"><i class="fas fa-qrcode"></i> Your Claim QR Code</h5>
                        <p class="card-text text-muted small">Present this unique QR code at authorized claiming centers for your pending assistance.</p>
                        <div class="qr-code mb-3">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?data=Farmer12345SubsidyClaim&size=200x200" alt="Claim QR Code">
                        </div>
                        <p class="fw-bold text-success mb-3">Farmer ID: <span class="text-primary">FRM-123456789</span></p>
                        <button class="btn btn-theme col-lg-4 col-md-6 mx-auto"><i class="fas fa-download me-2"></i> Download QR Code</button>
                        <p class="text-muted small mt-3">Always keep your QR code secure. It is unique to your approved claims.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>