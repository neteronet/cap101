<?php
session_start(); // Start the session at the very beginning of the script

// --- HELPER FUNCTION: Status to CSS Class Mapping (Copied from dashboard) ---
function get_status_class($status) {
    $status = strtolower($status);
    if (strpos($status, 'pending') !== false || strpos($status, 'review') !== false) {
        return 'status-pending';
    } elseif (strpos($status, 'approved') !== false || strpos($status, 'claimed') !== false) {
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

include '../includes/connection.php'; // Ensure your connection file is correctly included

// --- IMPROVEMENT 1: Robust Connection Check ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? "Connection object not set"));
    // Redirect to login on critical error
    header("location: farmers-login.php");
    exit();
}

// --- Check if the user is logged in ---
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: farmers-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Farmer'; // Default fallback
$farmer_id_display = "FRM-" . str_pad($user_id, 9, '0', STR_PAD_LEFT);
$is_farmer = false; // Flag for explicit farmer check

// --- IMPROVEMENT 2 & 3: Fetch Name AND User Type for Security Check ---
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

    // --- Explicit Farmer Authorization Check ---
    if ($db_user_type === 'farmer') {
        $is_farmer = true;
    } else {
        // If not a farmer, destroy session and redirect
        session_destroy();
        header("location: farmers-login.php");
        exit();
    }
} else {
    error_log("Failed to prepare statement for user name/type: " . $conn->error);
    // Treat preparation failure as a security risk/critical error
    session_destroy();
    header("location: farmers-login.php");
    exit();
}

// --- Fetch Claim History for Approved and Claimed Assistance ---
$claim_history = [];
$stmt_claims = $conn->prepare("
    SELECT sc.claim_id, aa.assistance_type, aa.seed_type, aa.seed_quantity, aa.engine_type, sc.claim_date, sc.notes
    FROM subsidy_claims sc
    JOIN assistance_applications aa ON sc.application_id = aa.application_id
    WHERE sc.user_id = ? AND aa.status = 'Approved' AND aa.claimed = 1
    ORDER BY sc.claim_date DESC
");
if ($stmt_claims) {
    $stmt_claims->bind_param("i", $user_id);
    $stmt_claims->execute();
    $stmt_claims->bind_result($claim_id, $assistance_type, $seed_type, $seed_quantity, $engine_type, $claim_date, $notes);
    while ($stmt_claims->fetch()) {
        $claim_history[] = [
            'claim_id' => $claim_id,
            'assistance_type' => $assistance_type,
            'seed_type' => $seed_type,
            'seed_quantity' => $seed_quantity,
            'engine_type' => $engine_type,
            'claim_date' => $claim_date,
            'notes' => $notes
        ];
    }
    $stmt_claims->close();
} else {
    error_log("Failed to prepare claim history statement: " . $conn->error);
}

// --- Fetch Application History ---
$application_history = [];
$stmt_apps = $conn->prepare("
    SELECT application_id, assistance_type, seed_type, seed_quantity, engine_type, status, application_date, remarks
    FROM assistance_applications
    WHERE user_id = ?
    ORDER BY application_date DESC
");
if ($stmt_apps) {
    $stmt_apps->bind_param("i", $user_id);
    $stmt_apps->execute();
    $stmt_apps->bind_result($app_id, $app_assistance_type, $app_seed_type, $app_seed_quantity, $app_engine_type, $app_status, $app_date, $app_remarks);
    while ($stmt_apps->fetch()) {
        $application_history[] = [
            'application_id' => $app_id,
            'assistance_type' => $app_assistance_type,
            'seed_type' => $app_seed_type,
            'seed_quantity' => $app_seed_quantity,
            'engine_type' => $app_engine_type,
            'status' => $app_status,
            'application_date' => $app_date,
            'remarks' => $app_remarks
        ];
    }
    $stmt_apps->close();
} else {
    error_log("Failed to prepare application history statement: " . $conn->error);
}

$conn->close(); // Close the connection after all database operations

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Farmer Account - Claim History</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts (CONSISTENT FONT STYLING) -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Custom Styles (UPDATED FOR CONSISTENCY) -->
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
        
        /* Sidebar Menu Label Style (CONSISTENT) */
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

        /* Header Brand (Logo and Text) (CONSISTENT) */
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
            justify-content: space-between; /* Changed to space-between for toggle button */
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
            padding: 72px 2rem 2rem 2rem;
            background: #f8f9fa;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        main.collapsed {
            margin-left: 0;
        }
        
        /* Typography Consistency */

        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .card-title,
        .modal-title,
        .page-title {
            /* MODIFIED: Explicitly set headings/titles font to Be Vietnam Pro */
            font-family: "Be Vietnam Pro", sans-serif;
            color: #0f5132;
            /* Dark Green */
        }
        
        /* MODIFIED: Match Dashboard's smaller page title size */
        .page-title {
            font-size: 1.5rem; /* Changed from 1.8rem */
            font-weight: 600;
            color: #0f5132;
            margin-bottom: 0.5rem; /* Changed from 1rem */
        }

        /* NEW: Style for the Dashboard Description Paragraph (Consistent with Dashboard) */
        .dashboard-description {
            font-size: 0.875rem; /* 14px */
        }
        
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            border: 1px solid #ddd; /* Consistent border */
        }

        /* Card Title Consistency */
        .card-title {
            color: #0f5132;
            font-size: 1.25rem;
            font-weight: 600; /* Added slight boldness */
            margin-bottom: 0.75rem;
        }
        
        /* NEW: Explicit Standard Card Text Size for Consistency (~15px) */
        .card-text, 
        .card-body p:not(.card-title), 
        .list-unstyled li {
            /* Inherits Poppins from body */
            font-size: 0.9375rem; /* ~15px for better readability in main content */
        }
        .card-text.small, 
        .list-unstyled.small li, 
        .card-text.text-muted.small {
            font-size: 0.875rem !important; /* Keep 14px for elements explicitly marked as small */
        }
        /* END NEW */


        .table thead th {
            background-color: #19860f;
            color: #fff;
            /* MODIFIED: setting explicitly to Be Vietnam Pro for UI consistency */
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .table td {
            vertical-align: middle;
            font-size: 0.9375rem; /* Consistent body font size */
        }

        .btn-theme {
            background-color: #19860f;
            color: #fff;
            border-color: #19860f;
            /* MODIFIED: setting explicitly to Be Vietnam Pro for UI consistency */
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .btn-theme:hover {
            background-color: #146c0b;
            border-color: #146c0b;
            color: #fff;
        }
        
        /* ADDED: Disabled Button Styling (Consistent with Dashboard) */
        .btn-theme.disabled {
            background-color: #7ab372; /* Lighter shade of theme color for disabled */
            border-color: #7ab372;
            pointer-events: none; /* Ensure no click */
            opacity: 0.65;
        }

        /* ADDED: Outline Button Theme (Consistent with Dashboard) */
        .btn-outline-theme {
            color: #19860f;
            border-color: #19860f;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .btn-outline-theme:hover,
        .btn-outline-theme:active {
            background-color: #146c0b;
            color: #fff;
            border-color: #146c0b;
        }

        .btn-outline-theme:focus {
            box-shadow: 0 0 0 0.25rem rgba(25, 134, 15, 0.5);
        }
        /* END ADDED BUTTON STYLES */

        #sidebarToggleBtn {
            color: #0f5132;
        }

        #sidebarToggleBtn:hover {
            color: #146c0b;
        }
        
        /* Custom status badge classes (CONSISTENT) */
        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.4rem;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
            /* MODIFIED: setting explicitly to Be Vietnam Pro for UI consistency */
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
        
        /* ADDED: Bootstrap default badge overrides for consistency with Dashboard */
        .text-info { color: #0dcaf0 !important; }
        .text-success { color: #198754 !important; }
        .text-warning { color: #ffc107 !important; }
        .text-danger { color: #dc3545 !important; }

        /* ADDED: Custom Alert Styles (Consistent with Dashboard) */
        .alert-custom-warning {
            background-color: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
            border-radius: .375rem;
            padding: .75rem 1rem;
            display: flex;
            gap: .75rem;
            align-items: flex-start;
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 0.9375rem; 
        }

        .alert-custom-warning i {
            margin-top: .2rem;
        }
        
        .alert-heading {
            color: inherit;
        }
        /* END ADDED CUSTOM ALERT STYLES */
    </style>

</head>

<body>

    <!-- Sidebar (CONSISTENT DESIGN) -->
    <nav class="sidebar">
        <!-- Logo and Text (Consistent) -->
        <a href="ProvincialAgriHome.html" class="header-brand">
            <img src="../photos/logo.png" alt="Department of Agriculture Logo" />
            <div>Agriconnect</div>
        </a>

        <!-- Menu Label (Consistent) -->
        <div class="sidebar-menu-label">Main Menu</div>

        <ul class="nav flex-column">
            <li class="nav-item"><a href="farmer-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="farmer-apply_for_assistance.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i>Apply for Assistance</a></li>
            <li class="nav-item"><a href="farmer-planting_status.php" class="nav-link"><i class="fas fa-leaf"></i> Planting Status</a></li>
            <li class="nav-item"><a href="farmer-claim_history.php" class="nav-link active"><i class="fas fa-history"></i> Claim History</a></li>
            <li class="nav-item"><a href="farmer-announcement.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li class="nav-item"><a href="farmer-my_profile.php" class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a></li>
        </ul>
        
        <!-- Logout Section (Consistent) -->
        <div class="sidebar-logout">
            <a href="farmers-logout.php" class="nav-link">
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
        <div class="container">

            <h1 class="page-title"><i></i>Claim History</h1>
            <!-- ADDED: dashboard-description class for consistent font size -->
            <p class="text-muted mb-4 dashboard-description">
                View the history of your approved and claimed assistance applications.
            </p>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-list me-2"></i>Approved Claims</h5>
                    <?php if (!empty($claim_history)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Claim ID</th>
                                        <th>Assistance Type</th>
                                        <th>Details</th>
                                        <th>Claim Date</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($claim_history as $claim): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($claim['claim_id']); ?></td>
                                            <td><?php echo htmlspecialchars($claim['assistance_type']); ?></td>
                                            <td>
                                                <?php
                                                $details = [];
                                                if ($claim['seed_type']) $details[] = "Seed: " . htmlspecialchars($claim['seed_type']);
                                                if ($claim['seed_quantity']) $details[] = "Qty: " . htmlspecialchars($claim['seed_quantity']);
                                                if ($claim['engine_type']) $details[] = "Engine: " . htmlspecialchars($claim['engine_type']);
                                                echo implode(", ", $details);
                                                ?>
                                            </td>
                                            <td><?php echo date('F j, Y', strtotime($claim['claim_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($claim['notes'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No approved claims found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-file-alt me-2"></i>Application History</h5>
                    <?php if (!empty($application_history)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Application ID</th>
                                        <th>Assistance Type</th>
                                        <th>Details</th>
                                        <th>Status</th>
                                        <th>Application Date</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($application_history as $app): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($app['application_id']); ?></td>
                                            <td><?php echo htmlspecialchars($app['assistance_type']); ?></td>
                                            <td>
                                                <?php
                                                $details = [];
                                                if ($app['seed_type']) $details[] = "Seed: " . htmlspecialchars($app['seed_type']);
                                                if ($app['seed_quantity']) $details[] = "Qty: " . htmlspecialchars($app['seed_quantity']);
                                                if ($app['engine_type']) $details[] = "Engine: " . htmlspecialchars($app['engine_type']);
                                                echo implode(", ", $details);
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status = htmlspecialchars($app['status']);
                                                // MODIFIED: Use the get_status_class helper function and status-badge class for consistency
                                                $badge_class = get_status_class($status);
                                                echo "<span class=\"status-badge $badge_class\">$status</span>";
                                                ?>
                                            </td>
                                            <td><?php echo date('F j, Y', strtotime($app['application_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($app['remarks'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No applications found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript for Sidebar Toggle (CONSISTENT DESIGN with localStorage) -->
    <script>
        // JavaScript to toggle sidebar collapse and preserve state using localStorage
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('main');
        const header = document.querySelector('.card-header-custom');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        // sidebarLinks variable is kept but its click listener is removed

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