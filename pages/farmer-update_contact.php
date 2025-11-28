<?php
session_start();

// NOTE: Assumes '../includes/connection.php' exists and provides a $conn object.
include '../includes/connection.php';

// --- IMPROVEMENT 1: Robust Connection Check ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in update_contact: " . ($conn->connect_error ?? "Connection object not set"));
    // Redirect to login on critical error
    header("location: farmers-login.php");
    exit();
}

// --- Check if the user is logged in (Security Check) ---
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: farmers-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Farmer'; // Default fallback
$is_farmer = false;
$current_contact_number = 'N/A';
$message = '';
$message_type = ''; // 'success', 'danger', 'warning', 'info'

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
        // Not a farmer, destroy session and redirect
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

// --- Fetch current contact number for the form and initial display ---
$stmt_fetch = $conn->prepare("SELECT contact_number FROM Farmers WHERE user_id = ?");
if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $user_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($data = $result->fetch_assoc()) {
        $current_contact_number = htmlspecialchars($data['contact_number']);
    }
    $stmt_fetch->close();
} else {
    error_log("Failed to prepare fetch contact statement: " . $conn->error);
    $message = "A critical error occurred while fetching current data.";
    $message_type = 'danger';
}

// --- Form Submission Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_contact'])) {
    $new_contact_number = trim($_POST['new_contact_number'] ?? '');

    // Basic Validation: Check if the number is not empty
    if (empty($new_contact_number)) {
        $message = "Contact number cannot be empty.";
        $message_type = 'warning';
    } 
    // Additional validation: Basic digit check (allows digits, hyphens, spaces, and parentheses)
    else if (!preg_match('/^[0-9\-\(\)\s]+$/', $new_contact_number)) {
        $message = "Invalid format. Please enter a valid number (digits, hyphens, spaces, and parentheses allowed).";
        $message_type = 'warning';
    }
    // Check if the new number is the same as the current number
    else if ($new_contact_number === $current_contact_number) {
        $message = "The new contact number is the same as your current one. No update performed.";
        $message_type = 'info';
    }
    else {
        // Sanitize and prepare update
        $sanitized_contact = htmlspecialchars($new_contact_number, ENT_QUOTES, 'UTF-8');
        
        $stmt_update = $conn->prepare("UPDATE Farmers SET contact_number = ? WHERE user_id = ?");

        if ($stmt_update) {
            $stmt_update->bind_param("si", $sanitized_contact, $user_id);
            
            if ($stmt_update->execute()) {
                // Update successful
                $message = "Your contact number has been successfully updated to: <strong>" . $sanitized_contact . "</strong>.";
                $message_type = 'success';
                // Update the displayed current number immediately
                $current_contact_number = $sanitized_contact; 
            } else {
                // Update failed
                $message = "Error updating contact number: " . $stmt_update->error;
                $message_type = 'danger';
                error_log("DB Update Error: " . $stmt_update->error);
            }
            $stmt_update->close();
        } else {
            // Statement preparation failed
            $message = "A critical database error occurred. Could not prepare update statement.";
            $message_type = 'danger';
            error_log("Failed to prepare update contact statement: " . $conn->error);
        }
    }
}

// Close connection at the end of PHP script execution
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Update Contact - Farmer Portal</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts (UPDATED FOR CONSISTENCY) -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <style>
        /* --- START CONSISTENT DESIGN STYLES (FROM DASHBOARD/PROFILE) --- */
        body {
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
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .sidebar.collapsed {
            left: -250px;
        }

        .sidebar-menu-label {
            color: rgba(255, 255, 255, 0.7);
            padding: 0 1rem 0.5rem 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        /* Typography Consistency: Card Titles & Headings */
        h1, h2, h3, h4, h5, h6, .card-title, .modal-title, .page-title {
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

        .card-text, .card-body p:not(.card-title):not(.summary-stat p), .list-unstyled li, .form-control {
            font-size: 0.9375rem;
        }

        /* Button and Alert Unification: Button Theme */
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

        .btn-outline-theme {
            color: #19860f;
            border-color: #19860f;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .btn-outline-theme:hover {
            background-color: #146c0b;
            color: #fff;
            border-color: #146c0b;
        }

        /* Custom alerts using consistent palette */
        .alert-custom-success {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
            border-radius: .375rem;
            padding: .75rem 1rem;
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 0.9375rem;
        }

        .alert-custom-danger {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
            border-radius: .375rem;
            padding: .75rem 1rem;
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 0.9375rem;
        }

        .alert-custom-warning {
            background-color: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
            border-radius: .375rem;
            padding: .75rem 1rem;
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 0.9375rem;
        }

        .alert-custom-info {
            background-color: #cff4fc;
            color: #055160;
            border: 1px solid #b6effb;
            border-radius: .375rem;
            padding: .75rem 1rem;
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 0.9375rem;
        }

        #sidebarToggleBtn {
            color: #0f5132;
        }
        
        /* General card styling for consistency */
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            border: 1px solid #ddd;
        }

        /* Notification Styles (Copied for Header Consistency) */
        .notification-bell-container {
            position: relative;
            display: inline-block;
        }

        .notification-bell {
            color: #0f5132;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.25rem;
            padding: 0;
            line-height: 1;
            transition: color 0.2s ease;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -10px;
            padding: 0.15em 0.45em;
            border-radius: 50%;
            background-color: #dc3545;
            color: white;
            font-size: 0.6rem;
            line-height: 1;
            min-width: 18px;
            text-align: center;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .notification-badge.hidden {
            display: none;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 320px;
            background-color: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-radius: 0.5rem;
            margin-top: 8px;
            z-index: 1070;
            display: none;
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 0.875rem;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #eee;
        }

        .notification-header h6 {
            margin: 0;
            font-size: 1rem;
            color: #0f5132;
            font-weight: 600;
        }

        .mark-all-read {
            color: #19860f;
            background: none;
            border: none;
            font-size: 0.75rem;
            cursor: pointer;
            padding: 0;
            text-decoration: underline;
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
            padding: 0;
        }

        .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f8f9fa;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .notification-item.unread {
            background-color: #f7fff6;
            border-left: 3px solid #19860f;
            padding-left: calc(1rem - 3px);
            font-weight: 500;
        }

        .notification-item p {
            margin: 0;
            line-height: 1.4;
        }

        /* --- UPDATE-SPECIFIC STYLES --- */
        .info-label {
            font-weight: 600;
            margin-right: 0.5rem;
            color: #555;
            min-width: 150px;
            display: inline-block;
            font-size: 0.9375rem;
        }

        .form-label {
            font-weight: 600;
            color: #0f5132;
            font-size: 0.9375rem;
        }

        /* Ensure input focus style is consistent */
        .form-control:focus {
            border-color: #a0dd9e; /* Lighter theme green */
            box-shadow: 0 0 0 0.25rem rgba(25, 134, 15, 0.25);
        }

        /* --- END CONSISTENT DESIGN STYLES --- */
    </style>
</head>

<body>

    <!-- Sidebar (CONSISTENT DESIGN) -->
    <nav class="sidebar">
        <a class="header-brand">
            <img src="../photos/logo.png" alt="Department of Agriculture Logo" />
            <div>Agriconnect</div>
        </a>

        <div class="sidebar-menu-label">Main Menu</div>

        <ul class="nav flex-column">
            <li class="nav-item"><a href="farmer-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="farmer-apply_for_assistance.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i>Apply for Assistance</a></li>
            <li class="nav-item"><a href="farmer-planting_status.php" class="nav-link"><i class="fas fa-leaf"></i> Planting Status</a></li>
            <li class="nav-item"><a href="farmer-claim_history.php" class="nav-link"><i class="fas fa-history"></i> Claim History</a></li>
            <li class="nav-item"><a href="farmer-announcement.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li class="nav-item"><a href="farmer-my_profile.php" class="nav-link active"><i class="fas fa-user-circle"></i> My Profile</a></li>
        </ul>
        <!-- Sidebar Logout Section (CONSISTENT DESIGN) -->
        <div class="sidebar-logout">
            <a href="farmers-logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Header (CONSISTENT DESIGN) -->
    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
        <!-- New Toggle Button (CONSISTENT) -->
        <button id="sidebarToggleBtn" class="btn btn-link p-0 text-dark" title="Toggle Sidebar" style="font-size: 1.5rem;">
            <i class="fas fa-bars"></i>
        </button>
        <!-- Right side alignment wrapper (ADDED) -->
        <div class="d-flex align-items-center">
            <!-- Greeting -->
            <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>

            <!-- Notification Bell (CONSISTENT) -->
            <div class="notification-bell-container me-3">
                <button class="notification-bell" id="notificationBell" onclick="toggleNotificationDropdown()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge hidden" id="notificationBadge">0</span>
                </button>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h6><i class="fas fa-bell me-2"></i>Notifications</h6>
                        <button class="mark-all-read" onclick="markAllAsRead()">Mark all as read</button>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="notification-loading">Loading notifications...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END MODIFIED HEADER -->

    <!-- Main Content -->
    <main>
        <div class="container-fluid">

            <!-- PAGE TITLE (CONSISTENT) -->
            <h1 class="page-title">Update Contact Information</h1>
            <p class="text-muted mb-4 dashboard-description">
                Use the form below to update your primary contact number.
            </p>
            <!-- END PAGE TITLE -->

            <!-- Back Button -->
            <p class="mb-3">
                <a href="farmer-my_profile.php" class="btn btn-outline-theme btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to Profile</a>
            </p>
            

            <!-- Feedback Message Display -->
            <?php if ($message): ?>
                <div class="alert-custom-<?php echo htmlspecialchars($message_type); ?> mb-4 shadow-sm" role="alert">
                    <?php if ($message_type === 'success'): ?>
                        <i class="fas fa-check-circle me-2 fs-5"></i>
                    <?php elseif ($message_type === 'danger'): ?>
                        <i class="fas fa-times-circle me-2 fs-5"></i>
                    <?php elseif ($message_type === 'warning'): ?>
                        <i class="fas fa-exclamation-triangle me-2 fs-5"></i>
                    <?php else: ?>
                        <i class="fas fa-info-circle me-2 fs-5"></i>
                    <?php endif; ?>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Update Form Card -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i class="fas fa-phone-alt me-2"></i>Edit Contact Number</h5>

                    <p class="card-text mb-4 border rounded p-3 bg-light">
                        <span class="info-label">Current Contact:</span>
                        <strong class="text-primary"><?php echo $current_contact_number; ?></strong>
                    </p>

                    <form method="POST" action="farmer-update_contact.php">
                        <div class="mb-4">
                            <label for="new_contact_number" class="form-label">New Contact Number</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="new_contact_number" 
                                   name="new_contact_number" 
                                   placeholder="e.g., 0917-xxxxxxx" 
                                   value="<?php echo (isset($_POST['new_contact_number']) && $message_type !== 'success') ? htmlspecialchars($_POST['new_contact_number']) : ''; ?>"
                                   required 
                                   maxlength="20">
                            <div class="form-text">Please enter your updated mobile or landline number.</div>
                        </div>

                        <button type="submit" name="update_contact" class="btn btn-theme px-4">
                            <i class="fas fa-save me-2"></i>Save New Contact
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript for Sidebar Toggle (CONSISTENT DESIGN - WITH localStorage) -->
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

        // Toggle button functionality (now uses state saving)
        toggleBtn.addEventListener('click', function() {
            if (sidebar.classList.contains('collapsed')) {
                openSidebar();
            } else {
                collapseSidebar();
            }
        });


        // --- Notification Bell JavaScript (COPIED FROM DASHBOARD) ---

        function toggleNotificationDropdown() {
            document.getElementById('notificationDropdown').classList.toggle('show');
            // Basic logic to hide the badge on open (in a real app, this would be an API call)
            document.getElementById('notificationBadge').classList.add('hidden');
        }

        // Close the dropdown if the user clicks outside of it
        window.onclick = function(event) {
            if (!event.target.matches('.notification-bell-container') && !event.target.closest('.notification-bell-container')) {
                var dropdowns = document.getElementsByClassName("notification-dropdown");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }

        function markAllAsRead() {
            // Placeholder for real logic (e.g., AJAX call)
            console.log("Marked all notifications as read.");
            document.getElementById('notificationList').innerHTML = '<div class="notification-item text-center text-muted small py-2">No new notifications.</div>';
        }

        // Simulate initial notification load 
        document.addEventListener('DOMContentLoaded', () => {
            // Simulate loading with 2 unread announcements
            const list = document.getElementById('notificationList');
            const badge = document.getElementById('notificationBadge');

            list.innerHTML = `
                <div class="notification-item unread">
                    <p class="mb-1">Your loan application has been <strong>Approved</strong>!</p>
                    <span class="text-muted small">5 minutes ago</span>
                </div>
                <div class="notification-item unread">
                    <p class="mb-1">New advisory on pest control for Rice crops.</p>
                    <span class="text-muted small">2 hours ago</span>
                </div>
                <div class="notification-item">
                    <p class="mb-1">Claim for Seed Subsidy is <strong>Ready</strong>.</p>
                    <span class="text-muted small">Yesterday</span>
                </div>
             `;
            badge.textContent = 2; // Set count
            badge.classList.remove('hidden'); // Show badge
        });
    </script>
</body>

</html>