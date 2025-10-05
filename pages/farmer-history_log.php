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

// --- Fetch History Log Entries (dummy data for now, replace with actual DB fetch) ---
$history_logs = [
    [
        'type' => 'planting',
        'icon' => 'fas fa-seedling',
        'message' => '<strong>Rice (Field 1)</strong> - Planting Status Updated: Seeds Planted 🌱 <br><em>Photo uploaded for verification.</em>',
        'date' => '2025-08-20 10:30:00'
    ],
    [
        'type' => 'warning',
        'icon' => 'fas fa-exclamation-triangle',
        'message' => '<strong>Corn (Field 2)</strong> - Planting Status Updated: Not yet planted ❌ <br><em>Delayed due to weather conditions.</em>',
        'date' => '2025-07-10 14:00:00'
    ],
    [
        'type' => 'activity',
        'icon' => 'fas fa-check-circle',
        'message' => '<strong>Vegetables (Plot 3)</strong> - Harvest Recorded: Harvested 🥕 <br><em>Yield: 150kg. Photo uploaded.</em>',
        'date' => '2025-06-01 09:00:00'
    ],
    [
        'type' => 'activity',
        'icon' => 'fas fa-tint',
        'message' => '<strong>Corn (Field 2)</strong> - Activity Logged: Initial Fertilization 🧪<br><em>Urea applied to 1 hectare.</em>',
        'date' => '2025-04-25 08:30:00'
    ],
    [
        'type' => 'danger',
        'icon' => 'fas fa-bug',
        'message' => '<strong>Vegetables (Plot 3)</strong> - Incident Reported: Minor Pest Infestation 🐛<br><em>Photo uploaded. Action: organic pesticide applied.</em>',
        'date' => '2025-04-05 16:15:00'
    ],
    [
        'type' => 'photo',
        'icon' => 'fas fa-camera',
        'message' => '<strong>Rice (Field 1)</strong> - Photo Uploaded: Growth Progress (30 days) 📸<br><em>Verification status: Approved.</em>',
        'date' => '2025-03-28 13:00:00'
    ],
    [
        'type' => 'subsidy',
        'icon' => 'fas fa-hand-holding-usd',
        'message' => '<strong>Subsidy Claimed:</strong> Fertilizer Assistance 💰<br><em>Claim ID: #ASST-2025-001.</em>',
        'date' => '2025-03-10 09:45:00'
    ],
    [
        'type' => 'profile',
        'icon' => 'fas fa-user-circle',
        'message' => '<strong>Profile Updated:</strong> Contact Information 📝<br><em>New mobile number: 09XX-XXX-XXXX.</em>',
        'date' => '2025-02-01 17:00:00'
    ],
];
// --- End Dummy History Log Entries ---

$conn->close(); // Close the connection after all database operations
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Account - History Log</title>
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

        /* --- Sidebar Styles --- */
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

        /* --- Fixed Top Header --- */
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

        .header-brand span {
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

        /* --- History Log Specific Styles --- */
        .log-entry {
            background: #f1fdf1; /* Light green background */
            border-left: 5px solid #28a745; /* Green accent */
            padding: 1rem 1.25rem;
            border-radius: 0.375rem; /* Consistent border-radius */
            margin-bottom: 0.75rem;
            display: flex;
            align-items: flex-start; /* Align items to the top for longer messages */
            box-shadow: 0 1px 3px rgba(0,0,0,0.08); /* Subtle shadow */
            transition: all 0.2s ease-in-out;
        }
        .log-entry:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }
        .log-entry i {
            margin-right: 15px; /* More space for icon */
            color: #28a745; /* Green icon */
            font-size: 1.4rem; /* Larger icon */
            min-width: 25px; /* Ensure icon doesn't shrink */
            text-align: center;
        }
        .log-entry strong {
            color: #333;
            font-size: 1.05rem;
        }
        .log-entry em {
            color: #555;
            font-size: 0.9em;
            display: block; /* Ensure emphasis text goes to new line if needed */
            margin-top: 4px;
        }
        .log-entry-content {
            flex-grow: 1;
        }
        .log-entry-date {
            font-size: 0.8rem;
            color: #999;
            margin-left: auto;
            white-space: nowrap; /* Prevent date from wrapping */
            padding-left: 15px; /* Space between content and date */
        }

        /* Specific styles for different log types */
        .log-entry.warning {
            border-left-color: #ffc107; /* Warning yellow */
            background-color: #fff3cd; /* Light yellow background */
        }
        .log-entry.warning i {
            color: #ffc107;
        }
        .log-entry.danger {
            border-left-color: #dc3545; /* Danger red */
            background-color: #f8d7da; /* Light red background */
        }
        .log-entry.danger i {
            color: #dc3545;
        }
        /* You can add more classes for different 'type' of logs if needed */
        .log-entry.info {
            border-left-color: #0d6efd;
            background-color: #cfe2ff;
        }
        .log-entry.info i {
            color: #0d6efd;
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
                <a href="farmer-history_log.php" class="nav-link active">
                    <i class="fas fa-history"></i> History Log
                </a>
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
            <h2 class="page-title"></i>History Log</h2>
            <p class="text-muted mb-4">A comprehensive record of your farm activities, crop updates, and important events.</p>

            <div class="row">
                <!-- Changed to a single full-width column, like the "Apply for Assistance" card on dashboard -->
                <div class="col-12"> 
                    <div class="card mb-4 h-100"> <!-- Added h-100 for consistent height if in a grid with other cards -->
                        <div class="card-body d-flex flex-column"> <!-- Added flex-column for consistent dashboard card body style -->
                            <h5 class="card-title"><i class="fas fa-clipboard-list me-2"></i>Activity Timeline</h5>
                            <p class="card-text text-muted small">Filter by crop or activity type (functionality to be added).</p>

                            <div class="timeline flex-grow-1"> <!-- flex-grow-1 to allow log entries to take available height -->
                                <?php if (!empty($history_logs)): ?>
                                    <?php foreach ($history_logs as $log_entry): ?>
                                        <div class="log-entry <?php echo htmlspecialchars($log_entry['type']); ?>">
                                            <i class="<?php echo htmlspecialchars($log_entry['icon']); ?>"></i>
                                            <div class="log-entry-content">
                                                <?php echo $log_entry['message']; ?>
                                            </div>
                                            <span class="log-entry-date"><?php echo date('F j, Y', strtotime($log_entry['date'])); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-info" role="alert">
                                        <i class="fas fa-info-circle me-2"></i>No history entries found.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Pagination/Load More could go here -->
                            <div class="text-center mt-4">
                                <button class="btn btn-outline-secondary"><i class="fas fa-redo me-1"></i>Load More Entries</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap Script -->
    <script src="https://cdn.jsdelivr="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>