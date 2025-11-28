<?php
session_start(); // Start the session at the very beginning of the script

include '../includes/connection.php'; // Ensure your connection file is correctly included

// --- IMPROVEMENT 1: Robust Connection Check to prevent crashing on DB failure ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? "Connection object not set"));
    // Redirect to a specific error page and stop execution
    header("location: admin-login.php"); 
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

// Example: Get count of all Farmers (which Admin can see)
$farmer_count = 0;
$stmt_count = $conn->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'farmer'");
if($stmt_count){
    $stmt_count->execute();
    $stmt_count->bind_result($farmer_count);
    $stmt_count->fetch();
    $stmt_count->close();
}


// --- NEW FETCH 1: Get count of all MAO Users (user_type = 'mao') ---
$mao_count = 0;
$stmt_mao = $conn->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'mao'");
if($stmt_mao){
    $stmt_mao->execute();
    $stmt_mao->bind_result($mao_count);
    $stmt_mao->fetch();
    $stmt_mao->close();
}


// --- NEW FETCH 2: Get count of Approved Applications ---
// NOTE: This assumes an 'applications' table with a 'status' column set to 'approved'.
// ADJUST table/column names if your schema is different.
$approved_applications_count = 0;
$stmt_applications = $conn->prepare("SELECT COUNT(*) FROM applications WHERE status = 'approved'");
if($stmt_applications){
    $stmt_applications->execute();
    $stmt_applications->bind_result($approved_applications_count);
    $stmt_applications->fetch();
    $stmt_applications->close();
}


// --- NEW FETCH 3: Get Recent Farmer Registrations (e.g., last 5) ---
$recent_farmers = [];
// Select the latest 5 farmers, ordered by user_id DESC (assuming auto-increment ID represents registration order)
// We fetch user_id, name, and email for display.
$stmt_recent = $conn->prepare("SELECT user_id, name, email FROM users WHERE user_type = 'farmer' ORDER BY user_id DESC LIMIT 5");

if ($stmt_recent) {
    $stmt_recent->execute();
    $result_recent = $stmt_recent->get_result();

    if ($result_recent->num_rows > 0) {
        while ($row = $result_recent->fetch_assoc()) {
            // Sanitize data before storing it in the array
            $row['name'] = htmlspecialchars($row['name']);
            $row['email'] = htmlspecialchars($row['email']);
            $recent_farmers[] = $row;
        }
    }
    $stmt_recent->close();
} else {
    error_log("Failed to prepare statement for recent farmers: " . $conn->error);
}


// --- Placeholder for other data fetches... ---

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin - Dashboard</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts (FROM FARMER DASHBOARD) -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Notification Bell Component Removed -->

    <!-- Custom Styles (CSS is synchronized with farmer-dashboard.php) -->
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

        /* --- Sidebar Styles (Consistent with Farmer Dashboard) --- */
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

        /* --- Fixed Top Header (Consistent with Farmer Dashboard) --- */
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

        /* --- Main Content Area (Consistent with Farmer Dashboard) --- */
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

        /* Typography Consistency */
        h1, h2, h3, h4, h5, h6, .card-title, .page-title {
            font-family: "Be Vietnam Pro", sans-serif;
            color: #0f5132;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .dashboard-description {
            font-size: 0.875rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .card-text, .card-body p:not(.card-title), .list-unstyled li {
            font-size: 0.9375rem;
        }

        /* Button Theme Consistency */
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
        
        /* Card Styles */
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            border: 1px solid #ddd;
        }
        
        /* Status Badge Consistency (Not heavily used by Admin, but for consistency) */
        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.4rem;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
            font-family: "Be Vietnam Pro", sans-serif;
        }
        
        /* Notification Bell Styling - REMOVED */

        /* Table Styling for better look */
        .table-striped>tbody>tr:nth-of-type(odd)>* {
            background-color: rgba(25, 134, 15, 0.05); /* Light green tint for stripes */
        }
        
        .table thead th {
            border-bottom: 2px solid #19860f;
        }

    </style>

</head>

<body>

    <!-- Sidebar (Consistent with Farmer Dashboard Structure) -->
    <nav class="sidebar">
        <!-- Logo and Text (Consistent Logo and Name for System) -->
        <a href="ProvincialAgriHome.html" class="header-brand">
            <img src="../photos/logo.png" alt="Department of Agriculture Logo" />
            <div>Agriconnect</div>
        </a>

        <!-- Menu Label -->
        <div class="sidebar-menu-label">Main Menu</div>

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
        </ul>
        
        <!-- Logout Section (Consistent location) -->
        <div class="sidebar-logout">
            <a href="admin-logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Header (Consistent with Farmer Dashboard Structure) -->
    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
        <!-- Sidebar Toggle Button -->
        <button id="sidebarToggleBtn" class="btn btn-link p-0 text-dark" title="Toggle Sidebar" style="font-size: 1.5rem;">
            <i class="fas fa-bars"></i>
        </button>
        <!-- Right side alignment wrapper -->
        <div class="d-flex align-items-center">
            <!-- Greeting -->
            <span class="me-3">Hi, <strong><?php echo $display_name; ?></strong></span>

            <!-- Notification Bell (Markup removed) -->
        </div>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <!-- Page Title (Consistent Style) -->
            <h1 class="page-title">Admin Dashboard</h1>
            <p class="text-muted mb-4 dashboard-description">
                Welcome to your administration panel. Review overall system performance and manage users.
            </p>

            <!-- Dashboard Content Cards -->
            <div class="row">
                <div class="col-md-4 mb-4">
                    <!-- Card 1: Registered Farmers (Existing) -->
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title text-white"><i class="fas fa-users me-2"></i> Registered Farmers</h5>
                            <p class="card-text fs-2">
                                <?php echo number_format($farmer_count); ?>
                            </p>
                            <a href="admin-view_farmers.php" class="text-white small text-decoration-none">View Details <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <!-- Card 2: Approved Applications (Updated Fetch) -->
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title text-white"><i class="fas fa-check-circle me-2"></i> Approved Applications</h5>
                            <p class="card-text fs-2">
                                <?php echo number_format($approved_applications_count); // Display fetched count ?>
                            </p>
                            <a href="#" class="text-white small text-decoration-none">More Info <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <!-- Card 3: MAO Users (Updated Fetch) -->
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title text-white"><i class="fas fa-user-tie me-2"></i> MAO Users</h5>
                            <p class="card-text fs-2">
                                <?php echo number_format($mao_count); // Display fetched count ?>
                            </p>
                            <a href="#" class="text-white small text-decoration-none">Manage Users <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- NEW: Recent Farmer Registrations Table -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Recent Farmer Registrations</h5>
                    
                    <div class="table-responsive">
                        <?php if (count($recent_farmers) > 0): ?>
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th scope="col" style="width: 10%;">ID</th>
                                    <th scope="col" style="width: 35%;">Name</th>
                                    <th scope="col" style="width: 40%;">Email</th>
                                    <th scope="col" style="width: 15%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_farmers as $farmer): ?>
                                <tr>
                                    <th scope="row"><?php echo $farmer['user_id']; ?></th>
                                    <td><?php echo $farmer['name']; ?></td>
                                    <td><?php echo $farmer['email']; ?></td>
                                    <td>
                                        <!-- Note: Replace 'admin-view_farmer_details.php' with your actual detail page link -->
                                        <a href="admin-view_farmer_details.php?id=<?php echo $farmer['user_id']; ?>" class="btn btn-sm btn-outline-success">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p class="text-muted mb-0">No recent farmer registrations found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript for Sidebar Toggle (Consistent with Farmer Dashboard) -->
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

        // Apply saved state on page load
        const isCollapsed = localStorage.getItem('sidebarCollapsed');
        if (isCollapsed === 'true') {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('collapsed');
            header.classList.add('collapsed');
        }

        // Toggle button functionality 
        toggleBtn.addEventListener('click', function() {
            if (sidebar.classList.contains('collapsed')) {
                openSidebar();
            } else {
                collapseSidebar();
            }
        });
        
        // Notification Bell Placeholder Functions - REMOVED

    </script>
</body>

</html>
<?php
// Close the connection as the very last step after all HTML and data have been generated
if (isset($conn) && $conn) {
    $conn->close();
}
?>