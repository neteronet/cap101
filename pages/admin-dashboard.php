<?php
session_start(); // Start the session at the very beginning of the script

include '../includes/connection.php'; // Ensure your connection file is correctly included

// --- Connection Check ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? "Connection object not set"));
    header("location: admin-login.php"); 
    exit();
}

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: admin-login.php");
    exit();
}
if ($_SESSION['user_type'] != 'admin') {
    header("location: admin-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Admin'; 
$is_admin = false;

// --- Fetch user name AND user type ---
$stmt_name = $conn->prepare("SELECT name, user_type FROM users WHERE user_id = ?");
if ($stmt_name) {
    $stmt_name->bind_param("i", $user_id);
    $stmt_name->execute();
    $stmt_name->bind_result($db_name, $db_user_type);
    $stmt_name->fetch();
    $stmt_name->close();

    if ($db_name) {
        $display_name = htmlspecialchars($db_name);
    }

    if ($db_user_type === 'admin') {
        $is_admin = true;
    } 
} else {
    error_log("Failed to prepare statement for user name/type: " . $conn->error);
}

// Security Check
if (!$is_admin) {
    session_unset();
    session_destroy();
    header("location: admin-login.php");
    exit();
}

// Get count of Farmers
$farmer_count = 0;
$stmt_count = $conn->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'farmer'");
if($stmt_count){
    $stmt_count->execute();
    $stmt_count->bind_result($farmer_count);
    $stmt_count->fetch();
    $stmt_count->close();
}

// Get count of MAO Users
$mao_count = 0;
$stmt_mao = $conn->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'mao'");
if($stmt_mao){
    $stmt_mao->execute();
    $stmt_mao->bind_result($mao_count);
    $stmt_mao->fetch();
    $stmt_mao->close();
}


// --- FETCH: Get Recent Added USERS --- 
$recent_users = [];

// We fetch user_id, name, type, and date.
$stmt_recent = $conn->prepare("SELECT user_id, name, user_type, created_at FROM users ORDER BY created_at DESC LIMIT 5");

if ($stmt_recent) {
    $stmt_recent->execute();
    $result_recent = $stmt_recent->get_result();

    if ($result_recent->num_rows > 0) {
        while ($row = $result_recent->fetch_assoc()) {
            $row['name'] = htmlspecialchars($row['name']);
            $row['formatted_date'] = date("M d, Y", strtotime($row['created_at']));
            $recent_users[] = $row;
        }
    }
    $stmt_recent->close();
} else {
    error_log("Failed to prepare statement for recent users: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin - Dashboard</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Custom Styles -->
    <style>
        body {
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
        
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            border: 1px solid #ddd;
        }
        
        /* Status Badge for User Type */
        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.4rem;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            text-transform: capitalize;
        }
        .bg-role-farmer { background-color: #d1e7dd; color: #0f5132; }
        .bg-role-mao { background-color: #cfe2ff; color: #084298; }
        .bg-role-admin { background-color: #e2e3e5; color: #41464b; }

        .table-striped>tbody>tr:nth-of-type(odd)>* {
            background-color: rgba(25, 134, 15, 0.05); 
        }
        
        .table thead th {
            border-bottom: 2px solid #19860f;
        }

    </style>

</head>

<body>

    <!-- Sidebar -->
    <nav class="sidebar">
        <a href="ProvincialAgriHome.html" class="header-brand">
            <img src="../photos/logo.png" alt="Department of Agriculture Logo" />
            <div>Agriconnect</div>
        </a>

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
        
        <div class="sidebar-logout">
            <a href="admin-logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Header -->
    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
        <button id="sidebarToggleBtn" class="btn btn-link p-0 text-dark" title="Toggle Sidebar" style="font-size: 1.5rem;">
            <i class="fas fa-bars"></i>
        </button>
        <div class="d-flex align-items-center">
            <span class="me-3">Hi, <strong><?php echo $display_name; ?></strong></span>
        </div>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h1 class="page-title">Admin Dashboard</h1>
            <p class="text-muted mb-4 dashboard-description">
                Welcome to your administration panel. Review overall system performance and manage users.
            </p>

            <div class="row">
                <!-- Card 1: Registered Farmers -->
                <div class="col-md-6 mb-4"> 
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
                
                <!-- Card 2: MAO Users -->
                <div class="col-md-6 mb-4">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title text-white"><i class="fas fa-user-tie me-2"></i> MAO Users</h5>
                            <p class="card-text fs-2">
                                <?php echo number_format($mao_count); ?>
                            </p>
                            <p class="small text-white mb-0" style="height: 1.5rem;">&nbsp;</p> 
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- MODIFIED: Recent Added USERS Table (ID and Action Removed) -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Recent Added Users</h5>
                    
                    <div class="table-responsive">
                        <?php if (count($recent_users) > 0): ?>
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <!-- Action Removed. Adjusted widths: Name 50%, Role 25%, Date 25% -->
                                    <th scope="col" style="width: 50%;">Name</th>
                                    <th scope="col" style="width: 25%;">Role</th>
                                    <th scope="col" style="width: 25%;">Date Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): ?>
                                    <?php 
                                        // Determine badge class based on user_type
                                        $badgeClass = 'bg-role-admin'; // fallback
                                        if($user['user_type'] === 'farmer') $badgeClass = 'bg-role-farmer';
                                        if($user['user_type'] === 'mao') $badgeClass = 'bg-role-mao';
                                    ?>
                                <tr>
                                    <td><?php echo $user['name'] ?: '<span class="text-muted">No Name</span>'; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $badgeClass; ?>">
                                            <?php echo strtoupper($user['user_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['formatted_date']; ?></td>
                                    <!-- Action Button Logic Removed -->
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p class="text-muted mb-0">No recent user registrations found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript for Sidebar Toggle -->
    <script>
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('main');
        const header = document.querySelector('.card-header-custom');
        const toggleBtn = document.getElementById('sidebarToggleBtn');

        function collapseSidebar() {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('collapsed');
            header.classList.add('collapsed');
            localStorage.setItem('sidebarCollapsed', 'true');
        }

        function openSidebar() {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('collapsed');
            header.classList.remove('collapsed');
            localStorage.setItem('sidebarCollapsed', 'false');
        }

        const isCollapsed = localStorage.getItem('sidebarCollapsed');
        if (isCollapsed === 'true') {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('collapsed');
            header.classList.add('collapsed');
        }

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
<?php
if (isset($conn) && $conn) {
    $conn->close();
}
?>