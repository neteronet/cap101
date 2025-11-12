<?php
session_start(); // Start the session at the very beginning of the script

include '../includes/connection.php'; // Ensure your connection file is correctly included

// --- IMPROVEMENT 1: Robust Connection Check to prevent crashing on DB failure ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? "Connection object not set"));
    // Redirect to a specific error page and stop execution
    header("location: database_error.php"); 
    exit();
}

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: admin-login.php");
    exit();
}
if ($_SESSION['user_type'] != 'admin') {
        header("location: admin-login.php");
        exit();
    }

$user_id = $_SESSION['user_id'];
$display_name = 'Admin'; // Default fallback
$is_admin = false; // Flag to enforce admin access

// --- IMPROVEMENT 2: Fetch user name AND user type for security check ---
$stmt_name = $conn->prepare("SELECT name, user_type FROM users WHERE user_id = ?");
if ($stmt_name) {
    $stmt_name->bind_param("i", $user_id);
    $stmt_name->execute();
    $stmt_name->bind_result($db_name, $db_user_type);
    $stmt_name->fetch();
    $stmt_name->close();

    if ($db_name) {
        $display_name = htmlspecialchars($db_name); // Sanitize immediately
    }

    // --- IMPROVEMENT 3: Explicit Admin Authorization Check ---
    if ($db_user_type === 'admin') {
        $is_admin = true;
    } 

} else {
    error_log("Failed to prepare statement for user name/type: " . $conn->error);
}

// Security Check: If the user is not explicitly an 'admin', redirect them out.
if (!$is_admin) {
    session_unset();
    session_destroy();
    header("location: admin-login.php");
    exit();
}

// --- Placeholder for Dashboard Data Fetching (Improvement 4: Data Filtering Concept) ---
// This is where you would fetch your dashboard data, using a WHERE clause 
// to ensure you only fetch relevant data (e.g., provincial/admin level data).

// Example: Get count of all Farmers (which Admin can see)
$farmer_count = 0;
// Note: If 'farmer' users are linked to 'mao's via a municipal_id, you might 
// need to adjust the structure or ensure admin can see all farmers across all municipals.
$stmt_count = $conn->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'farmer'");
if($stmt_count){
    $stmt_count->execute();
    $stmt_count->bind_result($farmer_count);
    $stmt_count->fetch();
    $stmt_count->close();
}


// --- Placeholder for other data fetches... ---

// --- IMPROVEMENT 5: Move $conn->close() to the end of the script ---
// We close the connection only after all data fetching (including the dashboard content) is complete.
// We keep it at the end of the PHP block for better clarity/structure.

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <!-- ... (HTML head content remains the same) ... -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin - Dashboard</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Custom Styles (CSS is unchanged) -->
    <style>
        /* ... (Your CSS styles here) ... */
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
        <!-- ... (Sidebar content remains the same) ... -->
        <a href="ProvincialAgriHome.html" class="header-brand">
            <img src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Province of Antique" />
            <div>Province of Antique</div>
        </a>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="admin-dashboard.php" class="nav-link active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-add_user.php" class="nav-link">
                    <i class="fas fa-user-plus"></i> Add User
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-view_farmers.php" class="nav-link">
                    <i class="fas fa-users"></i> View Farmers
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-register_farmer.php" class="nav-link">
                    <i class="fas fa-clipboard-list"></i> Register Farmer Details
                </a>
            </li>
        </ul>
    </nav>

    <!-- Header -->
    <div class="card-header card-header-custom d-flex justify-content-end align-items-center">
        <span class="me-3">Hi, <strong><?php echo $display_name; ?></strong></span>
        <button class="logout-btn" onclick="location.href='admin-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h1 class="page-title">Admin Dashboard</h1>

            <!-- Dashboard Content Cards -->
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-users me-2"></i> Registered Farmers</h5>
                            <p class="card-text fs-2">
                                <?php echo number_format($farmer_count); ?>
                            </p>
                            <a href="admin-view_farmers.php" class="text-white small text-decoration-none">View Details <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                </div>
                
                <!-- Add more cards for MAO count, pending requests, etc. -->
                <div class="col-md-4 mb-4">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-check-circle me-2"></i> Approved Applications</h5>
                            <p class="card-text fs-2">0</p>
                            <a href="#" class="text-white small text-decoration-none">More Info <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-user-tie me-2"></i> MAO Users</h5>
                            <p class="card-text fs-2">0</p>
                            <a href="#" class="text-white small text-decoration-none">Manage Users <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Placeholder for recent activity table -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Recent Farmer Registrations</h5>
                    <p class="text-muted">... Table of recent registrations will go here ...</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php
// Close the connection as the very last step after all HTML and data have been generated
if (isset($conn) && $conn) {
    $conn->close();
}
?>