<?php
session_start();

include '../includes/connection.php'; // Ensure this path is correct

// Redirect if user_id is not set or not an integer
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: municipal-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Mao'; // Default fallback

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

// --- Announcement Management Logic ---

// Handle Announcement Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement_id'])) {
    $announcement_id_to_delete = filter_var($_POST['delete_announcement_id'], FILTER_VALIDATE_INT);

    if ($announcement_id_to_delete) {
        $delete_stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        if ($delete_stmt) {
            $delete_stmt->bind_param("i", $announcement_id_to_delete);
            if ($delete_stmt->execute()) {
                $_SESSION['message'] = "Announcement deleted successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error deleting announcement: " . $conn->error;
                $_SESSION['message_type'] = "danger";
            }
            $delete_stmt->close();
        } else {
            $_SESSION['message'] = "Failed to prepare delete statement: " . $conn->error;
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Invalid announcement ID for deletion.";
        $_SESSION['message_type'] = "danger";
    }
    // Redirect to prevent re-submission on refresh
    header("Location: municipal-announcements.php");
    exit();
}


// Fetch announcements from the database with search, filter, and pagination
$announcements = [];
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all'; // Default to 'all'

$items_per_page = 10; // Number of announcements per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

$where_clauses = [];
$params = [];
$param_types = "";

// Build WHERE clauses for search and category filter
if (!empty($search_query)) {
    $where_clauses[] = "(title LIKE ? OR content LIKE ?)";
    $params[] = "%" . $search_query . "%";
    $params[] = "%" . $search_query . "%";
    $param_types .= "ss";
}

if ($category_filter !== 'all') {
    $where_clauses[] = "category = ?";
    $params[] = $category_filter;
    $param_types .= "s";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Query to get total number of announcements for pagination
$count_sql = "SELECT COUNT(*) AS total FROM announcements " . $where_sql;
$count_stmt = $conn->prepare($count_sql);

if ($count_stmt) {
    if (!empty($params)) {
        // Need to reset params array for count query as it uses different types/structure
        $count_params = [];
        $count_param_types = "";
        
        // Re-build params for count query (excluding limit/offset)
        if (!empty($search_query)) {
            $count_params[] = "%" . $search_query . "%";
            $count_params[] = "%" . $search_query . "%";
            $count_param_types .= "ss";
        }
        if ($category_filter !== 'all') {
            $count_params[] = $category_filter;
            $count_param_types .= "s";
        }
        
        if (!empty($count_params)) {
            // Using call_user_func_array with references is safer, but spread operator (...) works in PHP 5.6+ and is cleaner.
            $count_stmt->bind_param($count_param_types, ...$count_params);
        }
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_announcements = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_announcements / $items_per_page);
    $count_stmt->close();
} else {
    error_log("Failed to prepare count statement: " . $conn->error);
    $total_announcements = 0;
    $total_pages = 1;
}

// Query to fetch announcements for the current page
// Re-prepare params for the main query including LIMIT/OFFSET
$params = [];
$param_types = "";

if (!empty($search_query)) {
    $params[] = "%" . $search_query . "%";
    $params[] = "%" . $search_query . "%";
    $param_types .= "ss";
}

if ($category_filter !== 'all') {
    $params[] = $category_filter;
    $param_types .= "s";
}

// *** MODIFICATION 1: Try both 'image_url' and 'image' columns for compatibility ***
// First, check which column exists in the database
$image_column = 'image_url'; // Default
try {
    $check_column = $conn->query("SHOW COLUMNS FROM announcements LIKE 'image%'");
    if ($check_column && $check_column->num_rows > 0) {
        $columns = [];
        while ($col = $check_column->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        // Prefer 'image_url' if it exists, otherwise use 'image'
        if (in_array('image_url', $columns)) {
            $image_column = 'image_url';
        } elseif (in_array('image', $columns)) {
            $image_column = 'image';
        }
    } else {
        // If no image column found, log warning but continue
        error_log("WARNING: No image column found in announcements table. Using default 'image_url'.");
    }
} catch (Exception $e) {
    // If query fails, log error and use default
    error_log("ERROR checking image column: " . $e->getMessage() . ". Using default 'image_url'.");
}

$sql = "SELECT id, title, category, content, {$image_column} as image_path, publish_date, 'Published' AS status
        FROM announcements " . $where_sql . "
        ORDER BY publish_date DESC
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    // Adjust parameter types for the LIMIT clause
    $param_types .= "ii";
    $params[] = $offset;
    $params[] = $items_per_page;

    // Use call_user_func_array for binding parameters dynamically
    // The bind_param takes a reference to the array elements, so we need to ensure the arguments are passed as references
    if (!empty($params)) {
        // Since $params contains the search/filter values AND the limit/offset values,
        // we can bind them all at once.
        $stmt->bind_param($param_types, ...$params);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Ensure status is always 'Published' as per your database schema context implies
                $row['status'] = 'Published';
                $announcements[] = $row;
            }
        }
        $stmt->close();
    } else {
        // Log execution error
        error_log("Error executing announcements query: " . $stmt->error);
        if (!isset($_SESSION['message'])) {
            $_SESSION['message'] = "Database error: Unable to fetch announcements. Error: " . htmlspecialchars($stmt->error);
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    }
} else {
    error_log("Error fetching announcements: " . $conn->error);
    // Set error message for user notification
    if (!isset($_SESSION['message'])) {
        $_SESSION['message'] = "Database error: Unable to fetch announcements. Please contact the administrator.";
        $_SESSION['message_type'] = "danger";
    }
}

$conn->close(); // Close the connection ONLY AFTER all queries are done

// Helper function to fix image path for display
function getImagePath($image_path) {
    if (empty($image_path) || $image_path === 'null' || $image_path === '') {
        return null;
    }
    
    // If path already starts with http:// or https://, return as is
    if (preg_match('/^https?:\/\//', $image_path)) {
        return $image_path;
    }
    
    // If path already starts with ../, return as is
    if (strpos($image_path, '../') === 0) {
        return $image_path;
    }
    
    // If path starts with uploads/, add ../ prefix
    if (strpos($image_path, 'uploads/') === 0) {
        return '../' . $image_path;
    }
    
    // If path starts with /, it's absolute, return as is
    if (strpos($image_path, '/') === 0) {
        return $image_path;
    }
    
    // Default: assume it's relative to uploads folder
    return '../uploads/announcements/' . $image_path;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Account - Announcements</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts (Updated for Consistency) -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

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

        /* --- Sidebar Styles (CONSISTENT WITH FARMER DASHBOARD) --- */
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

        /* --- Fixed Top Header (CONSISTENT WITH FARMER DASHBOARD) --- */
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

        #sidebarToggleBtn {
            color: #0f5132; /* Darker green */
        }

        #sidebarToggleBtn:hover {
            color: #146c0b;
        }

        /* --- Main Content Area --- */
        main {
            margin-left: 250px;
            padding: 72px 2rem 2rem 2rem; /* Adjusted top padding for fixed header */
            background: #f8f9fa;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        main.collapsed {
            margin-left: 0;
        }
        
        /* 5. Typography Consistency: Headings */
        h1, h2, h3, h4, h5, h6, .card-title, .modal-title, .page-title {
            font-family: "Be Vietnam Pro", sans-serif;
            color: #0f5132; /* Dark Green */
        }
        
        /* NEW: Style for the Page Title */
        .page-title {
            font-size: 1.5rem; 
            font-weight: 600; 
            margin-bottom: 0.5rem;
        }

        .dashboard-description {
            font-size: 0.875rem; /* 14px */
        }

        /* NEW: Explicit Card Title Size for Consistency */
        .card-title {
            font-size: 1.25rem; 
            font-weight: 600; 
        }

        /* NEW: Explicit Standard Card Text Size for Consistency (0.9375rem = 15px) */
        .card-text, .card-body p:not(.card-title), .list-unstyled li, .form-control, .form-select {
            font-size: 0.9375rem; 
        }

        /* 2. Button and Alert Unification: Button Theme */
        .btn-theme, .add-announcement-btn {
            background-color: #19860f;
            color: #fff;
            border-color: #19860f;
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 0.9375rem; /* 15px */
            padding: 8px 15px; /* Consistent padding */
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            border-radius: 0.25rem;
        }

        .btn-theme:hover, .add-announcement-btn:hover {
            background-color: #146c0b;
            border-color: #146c0b;
            color: #fff;
        }
        
        .add-announcement-btn i {
            margin-right: 5px;
        }
        
        /* REMOVED: Custom .logout-btn styling block for consistency with farmer dashboard header */

        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            border: 1px solid #ddd;
        }
        
        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.4rem;
            font-size: 13px;
            font-weight: 500;
            text-transform: capitalize;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .status-published {
            background-color: #198754; /* Success green */
            color: #fff;
        }

        /* Category Labels - Ensure consistent font/size */
        .category-label {
            padding: 0.25em 0.6em;
            border-radius: 0.3rem;
            font-size: 0.85em;
            font-weight: 600;
            color: #fff;
            text-transform: capitalize;
            font-family: "Be Vietnam Pro", sans-serif; /* Consistent font */
            margin-left: 5px; /* Added spacing */
        }
        
        /* Action Buttons - Consistency */
        .btn-info, .btn-primary, .btn-danger, .btn-warning {
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 0.875rem; /* 14px for small actions */
            padding: 0.375rem 0.75rem;
        }

        /* Custom fix for modal position to clear fixed header (56px height) */
        .announcement-modal-top-offset {
            /* Remove default centering effect by top margin */
            /* 56px (header height) + ~14px buffer = 70px */
            margin-top: 70px; 
            margin-bottom: 30px; /* Ensure some bottom space */
            /* Note: modal-dialog will retain its max-width/responsive behavior */
        }
        
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .page-info {
            font-size: 0.9em;
            color: #6c757d;
        }

        /* Announcement List Styles */
        .announcement-item {
            display: flex;
            align-items: flex-start;
            padding: 15px 0; 
            border-bottom: 1px solid #eee;
        }
        
        .announcement-item:last-child {
            border-bottom: none;
        }
        
        .announcement-image-container {
            width: 100px;
            height: 70px;
            overflow: hidden;
            flex-shrink: 0;
            margin-right: 15px;
            border-radius: 4px;
            border: 1px solid #eee;
        }
        
        .announcement-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .announcement-image-container .no-image-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background-color: #f1f1f1;
            color: #6c757d;
            font-size: 0.75rem;
            text-align: center;
        }

        .announcement-content-area {
            flex-grow: 1;
        }
        
        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .announcement-title {
            font-size: 1.05rem; /* Slightly smaller for list items */
            font-weight: 600;
            color: #0f5132; /* Use dark green for consistency */
            margin-bottom: 0;
            flex-grow: 1;
        }
        
        .announcement-meta {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .announcement-description {
            font-size: 0.875rem; /* 14px for description text */
            color: #495057;
            margin-bottom: 10px;
        }
        
        .announcement-actions .btn-sm {
            margin-right: 5px;
        }
        
    </style>
</head>

<body>
    <!-- Sidebar (CONSISTENT DESIGN) -->
    <nav class="sidebar">
        <!-- Logo and Text (Consistent with farmer-dashboard.php) -->
        <a href="municipal-dashboard.php" class="header-brand">
            <img src="../photos/logo.png" alt="Department of Agriculture Logo" />
            <div>Agriconnect</div>
        </a>
        
        <!-- Menu Label (Consistent) -->
        <div class="sidebar-menu-label">Main Menu</div>

        <ul class="nav flex-column">
            <li class="nav-item"><a href="municipal-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="municipal-subsidy_management.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i> Subsidy Management</a></li>
            <li class="nav-item"><a href="municipal-qrcode_management.php" class="nav-link"><i class="fas fa-qrcode"></i> QR Code Management</a></li>
            <li class="nav-item"><a href="municipal-crop_monitoring.php" class="nav-link"><i class="fas fa-seedling"></i> Crop Monitoring</a></li>
            <li class="nav-item"><a href="municipal-reports_analytics.php" class="nav-link"><i class="fas fa-chart-line"></i> Reports & Analytics</a></li>
            <li class="nav-item"><a href="municipal-farmer_profiles.php" class="nav-link"><i class="fas fa-users"></i> Farmer Profiles</a></li>
            <li class="nav-item"><a href="municipal-announcements.php" class="nav-link active"><i class="fas fa-bullhorn"></i> Announcements</a></li>
        </ul>
        
        <!-- Logout Section (Consistent) -->
        <div class="sidebar-logout">
            <a href="municipal-logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Header (CONSISTENT DESIGN - MATCHING FARMER DASHBOARD) -->
    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
        <!-- Sidebar Toggle Button (Consistent) -->
        <button id="sidebarToggleBtn" class="btn btn-link p-0 text-dark" title="Toggle Sidebar" style="font-size: 1.5rem;">
            <i class="fas fa-bars"></i>
        </button>
        <!-- Greeting -->
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
    </div>

    <!-- Main Content (UPDATED PADDING/MARGIN) -->
    <main>
        <div class="container-fluid">
            <!-- Page Title and Description (Consistent Typography) -->
            <h1 class="page-title">Announcements Management</h1>
            <p class="text-muted mb-4 dashboard-description">
                Manage, publish, and view all public announcements for the community.
            </p>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="add-announcement-btn-container">
                    <a href="municipal-add_announcement.php" class="add-announcement-btn">
                        <i class="fas fa-plus-circle"></i> Add New Announcement
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
                ?>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row mb-4 align-items-center search-filter-bar">
                        <div class="col-md-5 mb-3 mb-md-0">
                            <form method="GET" action="" class="d-flex">
                                <div class="input-group flex-grow-1">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" placeholder="Search by title or content..." name="search" value="<?php echo htmlspecialchars($search_query); ?>" />
                                    <button class="btn btn-theme" type="submit">Search</button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-3 offset-md-4">
                            <form method="GET" action="" id="categoryFilterForm">
                                <select class="form-select" id="categoryFilter" name="category" onchange="this.form.submit()">
                                    <option value="all" <?php echo ($category_filter == 'all' || empty($category_filter)) ? 'selected' : ''; ?>>All Categories</option>
                                    <option value="advisory" <?php echo ($category_filter == 'advisory') ? 'selected' : ''; ?>>Advisory</option>
                                    <option value="program" <?php echo ($category_filter == 'program') ? 'selected' : ''; ?>>Program</option>
                                    <option value="alert" <?php echo ($category_filter == 'alert') ? 'selected' : ''; ?>>Alert</option>
                                    <option value="general" <?php echo ($category_filter == 'general') ? 'selected' : ''; ?>>General Updates</option>
                                    <option value="agriculture" <?php echo ($category_filter == 'agriculture') ? 'selected' : ''; ?>>Agriculture</option>
                                </select>
                                <?php if (!empty($search_query)): ?>
                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <div class="announcement-card-body">
                        <?php if (!empty($announcements)): ?>
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="announcement-item">
                                    <div class="announcement-image-container">
                                        <?php 
                                        // *** MODIFICATION 2: Use helper function to fix image path ***
                                        $image_path = getImagePath($announcement['image_path'] ?? '');
                                        if ($image_path): ?>
                                            <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Announcement Image" onerror="this.parentElement.innerHTML='<span class=\'no-image-placeholder\'>Image Not Found</span>'">
                                        <?php else: ?>
                                            <span class="no-image-placeholder">No Image</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="announcement-content-area">
                                        <div class="announcement-header">
                                            <h5 class="announcement-title me-3"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                            <div class="d-flex align-items-center">
                                                <span class="status-badge status-<?php echo strtolower(htmlspecialchars($announcement['status'])); ?>">
                                                    <?php echo htmlspecialchars($announcement['status']); ?>
                                                </span>
                                                <span class="category-label announcement-category category-<?php echo strtolower(htmlspecialchars($announcement['category'])); ?>"
                                                      style="background-color: 
                                                            <?php 
                                                                // Simple category color logic
                                                                $cat = strtolower($announcement['category']);
                                                                if ($cat == 'advisory') echo '#0d6efd'; // Primary blue
                                                                else if ($cat == 'program') echo '#198754'; // Success green
                                                                else if ($cat == 'alert') echo '#dc3545'; // Danger red
                                                                else if ($cat == 'agriculture') echo '#ffc107'; // Warning yellow
                                                                else echo '#6c757d'; // Secondary grey
                                                            ?>;">
                                                    <?php echo htmlspecialchars($announcement['category']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <p class="announcement-meta">Published: <?php echo date('M d, Y', strtotime($announcement['publish_date'])); ?></p>
                                        <p class="announcement-description"><?php echo htmlspecialchars(substr($announcement['content'], 0, 150)); ?>...</p>
                                        <div class="announcement-actions">
                                            <button type="button" class="btn btn-info btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#announcementDetailModal"
                                                data-id="<?php echo $announcement['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                                                data-date="<?php echo date('M d, Y', strtotime($announcement['publish_date'])); ?>"
                                                data-category="<?php echo htmlspecialchars($announcement['category']); ?>"
                                                data-image="<?php echo htmlspecialchars($image_path ?? ''); ?>"
                                                data-content="<?php echo htmlspecialchars($announcement['content']); ?>"
                                                title="View Details">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <a href="municipal-edit_announcement.php?id=<?php echo $announcement['id']; ?>" class="btn btn-primary btn-sm" title="Edit Announcement">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <button type="button" class="btn btn-danger btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal"
                                                data-id="<?php echo $announcement['id']; ?>"
                                                title="Delete Announcement">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info text-center mt-3" role="alert">
                                No announcements found matching your criteria.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination-container">
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>&category=<?php echo urlencode($category_filter); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php 
                                    // Limit the number of pages shown for cleaner UI (e.g., 5 pages centered around current page)
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);

                                    if ($page - 2 < 1) {
                                        $end_page = min($total_pages, $end_page + (1 - ($page - 2)));
                                    }
                                    if ($page + 2 > $total_pages) {
                                        $start_page = max(1, $start_page - (($page + 2) - $total_pages));
                                    }
                                ?>
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&category=<?php echo urlencode($category_filter); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>&category=<?php echo urlencode($category_filter); ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <span class="page-info ms-3">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- Announcement Detail Modal -->
    <div class="modal fade" id="announcementDetailModal" tabindex="-1" aria-labelledby="announcementDetailModalLabel" aria-hidden="true">
        <!-- FIX: Removed 'modal-dialog-centered' and added 'announcement-modal-top-offset' -->
        <div class="modal-dialog modal-lg announcement-modal-top-offset"> 
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="announcementDetailModalLabel">Announcement Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h4 id="modalAnnouncementTitle" class="mb-2"></h4>
                    <p class="text-muted small">
                        <span id="modalAnnouncementDate" class="me-3"></span>
                        <span id="modalAnnouncementCategory" class="category-label"></span>
                    </p>
                    <img id="modalAnnouncementImage" src="" alt="Announcement Image" class="img-fluid mb-3 d-none">
                    <p id="modalAnnouncementContent" style="white-space: pre-wrap;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
        <!-- FIX: Removed 'modal-dialog-centered' and added 'announcement-modal-top-offset' -->
        <div class="modal-dialog announcement-modal-top-offset">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmationModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this announcement? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteAnnouncementForm" method="POST" action="municipal-announcements.php">
                        <input type="hidden" name="delete_announcement_id" id="deleteAnnouncementId">
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt me-2"></i>Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar Toggle Logic (Consistent with farmer-dashboard.php)
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

            // Toggle button functionality
            toggleBtn.addEventListener('click', function() {
                if (sidebar.classList.contains('collapsed')) {
                    openSidebar();
                } else {
                    collapseSidebar();
                }
            });
            
            // Announcement Detail Modal Logic
            const announcementDetailModal = document.getElementById('announcementDetailModal');
            announcementDetailModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget; // Button that triggered the modal
                const title = button.getAttribute('data-title');
                const date = button.getAttribute('data-date');
                const category = button.getAttribute('data-category');
                const image = button.getAttribute('data-image'); // This now correctly uses 'image'
                const content = button.getAttribute('data-content');

                const modalTitle = announcementDetailModal.querySelector('#modalAnnouncementTitle');
                const modalDate = announcementDetailModal.querySelector('#modalAnnouncementDate');
                const modalCategory = announcementDetailModal.querySelector('#modalAnnouncementCategory');
                const modalImage = announcementDetailModal.querySelector('#modalAnnouncementImage');
                const modalContent = announcementDetailModal
                    .querySelector('#modalAnnouncementContent');

                modalTitle.textContent = title;
                modalDate.textContent = `Published: ${date}`;
                modalCategory.textContent = category;
                
                // Update category class/style based on fetched data
                let catClass = `category-label`;
                let catStyle = '';
                const catLower = category.toLowerCase();
                
                // Apply style based on category
                if (catLower == 'advisory') catStyle = 'background-color: #0d6efd;';
                else if (catLower == 'program') catStyle = 'background-color: #198754;';
                else if (catLower == 'alert') catStyle = 'background-color: #dc3545;';
                else if (catLower == 'agriculture') catStyle = 'background-color: #ffc107;'; // Note: yellow on white might be hard to read
                else catStyle = 'background-color: #6c757d;';
                
                modalCategory.className = catClass; // Reset classes
                modalCategory.setAttribute('style', catStyle);
                
                modalContent.textContent = content;

                // Handle image display with error handling
                if (image && image !== 'null' && image !== '' && image.trim() !== '') {
                    modalImage.src = image;
                    modalImage.classList.remove('d-none');
                    // Add error handler for broken images
                    modalImage.onerror = function() {
                        this.classList.add('d-none');
                        console.warn('Failed to load image: ' + image);
                    };
                } else {
                    modalImage.classList.add('d-none');
                    modalImage.src = ''; // Clear src to prevent loading attempts
                }
            });

            // Delete Confirmation Modal Logic
            const deleteConfirmationModal = document.getElementById('deleteConfirmationModal');
            deleteConfirmationModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget; // Button that triggered the modal
                const announcementId = button.getAttribute('data-id');
                const deleteAnnouncementIdInput = deleteConfirmationModal.querySelector('#deleteAnnouncementId');
                deleteAnnouncementIdInput.value = announcementId;
            });

            // Ensure category filter form submits search query as well
            const categoryFilter = document.getElementById('categoryFilter');
            if (categoryFilter) {
                categoryFilter.addEventListener('change', function() {
                    const form = this.closest('form');
                    const currentSearch = new URLSearchParams(window.location.search).get('search');
                    // Check if hidden search input exists, if not, create it
                    let searchInput = form.querySelector('input[name="search"]');
                    if (!searchInput) {
                        searchInput = document.createElement('input');
                        searchInput.type = 'hidden';
                        searchInput.name = 'search';
                        form.appendChild(searchInput);
                    }
                    // Only set value if search query is present
                    searchInput.value = currentSearch || '';
                    
                    form.submit();
                });
            }
        });
    </script>
</body>

</html>