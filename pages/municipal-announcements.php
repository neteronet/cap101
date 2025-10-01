<?php
session_start(); // Start the session at the very beginning of the script

// Database connection details
$servername = "localhost";
$username_db = "root"; // Replace with your database username
$password_db = "";     // Replace with your database password
$dbname = "cap101"; // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Check connection
if ($conn->connect_error) {
    // Log error or display a generic message, but don't expose database details
    error_log("Database connection failed: " . $conn->connect_error);
    // You might want to redirect to an error page or show a friendly message
    die("Error connecting to the database. Please try again later."); // Critical error, stop execution
}

// Check if user is logged in and is a 'mao' user type
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'mao') {
    header("location: municipal-login.php"); // Redirect to MAO login page
    exit();
}

$display_name = "Guest"; // Default display name

// Fetch the display name from the database for the logged-in user
// Only fetch if the connection is successful and user_id is set
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($fetched_db_name);
        $stmt->fetch();
        if ($fetched_db_name) {
            $display_name = $fetched_db_name; // Use the name fetched from DB
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare statement for fetching user name: " . $conn->error);
    }
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
        $count_stmt->bind_param($param_types, ...$params);
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
$sql = "SELECT id, title, category, content, image_url, publish_date, 'Published' AS status
        FROM announcements " . $where_sql . "
        ORDER BY publish_date DESC
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    // Adjust parameter types for the LIMIT clause
    $param_types .= "ii";
    $params[] = $offset;
    $params[] = $items_per_page;

    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Ensure status is always 'Published' as per your database schema context implies
            $row['status'] = 'Published';
            $announcements[] = $row;
        }
    }
    $stmt->close();
} else {
    error_log("Error fetching announcements: " . $conn->error);
    // Optionally, display a user-friendly message
}

$conn->close(); // Close the connection ONLY AFTER all queries are done
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Account - Announcements</title>

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

        .add-announcement-btn {
            background-color: #19860f;
            color: #fff;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
        }

        .add-announcement-btn:hover {
            background-color: #146c0b;
            color: #fff;
        }

        .add-announcement-btn i {
            margin-right: 5px;
        }

        main {
            margin-left: 250px;
            padding: 1rem 2rem 2rem 2rem;
            padding-top: 72px;
            /* Adjust for fixed header */
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
            text-transform: capitalize;
            /* Make status look nicer */
        }

        .status-published {
            background-color: #28a745;
            /* Success green */
            color: #fff;
        }

        .status-draft {
            background-color: #ffc107;
            /* Warning yellow */
            color: #856404;
        }

        .table thead th {
            background-color: #f2f2f2;
            color: #555;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }

        .table tbody tr:hover {
            background-color: #f0f8f0;
        }

        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }

        .btn-info:hover {
            background-color: #138496;
            border-color: #138496;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
            /* Dark text for warning */
        }

        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #e0a800;
        }

        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #c82333;
            border-color: #c82333;
        }

        .modal-body img {
            max-width: 100%;
            height: auto;
            border-radius: 0.3rem;
        }

        .category-label {
            padding: 0.25em 0.6em;
            border-radius: 0.3rem;
            font-size: 0.85em;
            font-weight: 600;
            color: #fff;
            text-transform: capitalize;
        }

        .category-advisory {
            background-color: #007bff;
        }

        /* Blue */
        .category-program {
            background-color: #6f42c1;
        }

        /* Purple */
        .category-alert {
            background-color: #dc3545;
        }

        /* Red */
        .category-general {
            background-color: #6c757d;
        }

        /* Gray */
        .category-agriculture {
            background-color: #28a745;
        }

        /* Green, for your example */

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
        }

        .page-info {
            font-size: 0.9em;
            color: #6c757d;
        }

        /* New Card Body Design for Announcements */
        .announcement-card-body {
            display: flex;
            flex-direction: column;
        }

        .announcement-item {
            display: flex;
            align-items: flex-start;
            /* Align image and text at the top */
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .announcement-item:last-child {
            border-bottom: none;
        }

        .announcement-image-container {
            flex-shrink: 0;
            /* Prevent image from shrinking */
            width: 120px;
            /* Fixed width for image */
            height: 80px;
            /* Fixed height for image */
            margin-right: 15px;
            border-radius: 5px;
            overflow: hidden;
            background-color: #f0f0f0;
            /* Placeholder background */
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .announcement-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            /* Cover the container */
        }

        .announcement-image-container .no-image-placeholder {
            color: #bbb;
            font-size: 0.9rem;
            text-align: center;
        }

        .announcement-content-area {
            flex-grow: 1;
            /* Allow content to take remaining space */
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .announcement-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: #19860f;
            margin-bottom: 0;
        }

        .announcement-meta {
            font-size: 0.85rem;
            color: #777;
        }

        .announcement-category {
            margin-left: 10px;
        }

        .announcement-description {
            font-size: 0.95rem;
            color: #555;
            margin-top: 5px;
            /* Limit to 2 lines for brevity */
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .announcement-actions {
            margin-top: 10px;
            text-align: right;
            /* Align action buttons to the right */
        }

        .announcement-actions .btn {
            margin-left: 5px;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <a href="municipal-dashboard.php" class="header-brand">
            <img src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Province of Antique" />
            <div>Province of Antique</div>
        </a>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="municipal-dashboard.php" class="nav-link">
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
                <a href="municipal-announcements.php" class="nav-link active">
                    <i class="fas fa-bullhorn"></i> Announcements
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
            <div class="d-flex justify-content-between align-items-center mb-4">

                <div class="add-announcement-btn-container">
                    <!-- Link to a dummy add announcement page -->
                    <a href="municipal-add_announcement.php" class="add-announcement-btn">
                        <i class="fas fa-plus-circle"></i> Add Announcement
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
                                    <!-- Add the 'Agriculture' category from your example DB if needed -->
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
                                        <?php if (!empty($announcement['image_url']) && $announcement['image_url'] !== 'null'): ?>
                                            <img src="<?php echo htmlspecialchars($announcement['image_url']); ?>" alt="Announcement Image">
                                        <?php else: ?>
                                            <span class="no-image-placeholder">No Image</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="announcement-content-area">
                                        <div class="announcement-header">
                                            <h5 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                            <div class="d-flex align-items-center">
                                                <span class="status-badge status-<?php echo strtolower(htmlspecialchars($announcement['status'])); ?>">
                                                    <?php echo htmlspecialchars($announcement['status']); ?>
                                                </span>
                                                <span class="category-label announcement-category category-<?php echo strtolower(htmlspecialchars($announcement['category'])); ?>">
                                                    <?php echo htmlspecialchars($announcement['category']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <p class="announcement-meta">Published: <?php echo date('M d, Y', strtotime($announcement['publish_date'])); ?></p>
                                        <p class="announcement-description"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                        <div class="announcement-actions">
                                            <button type="button" class="btn btn-info btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#announcementDetailModal"
                                                data-id="<?php echo $announcement['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                                                data-date="<?php echo date('M d, Y', strtotime($announcement['publish_date'])); ?>"
                                                data-category="<?php echo htmlspecialchars($announcement['category']); ?>"
                                                data-image="<?php echo htmlspecialchars($announcement['image_url']); ?>"
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
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
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
        <div class="modal-dialog modal-dialog-centered modal-lg">
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
        <div class="modal-dialog modal-dialog-centered">
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
            // Announcement Detail Modal Logic
            const announcementDetailModal = document.getElementById('announcementDetailModal');
            announcementDetailModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget; // Button that triggered the modal
                const title = button.getAttribute('data-title');
                const date = button.getAttribute('data-date');
                const category = button.getAttribute('data-category');
                const image = button.getAttribute('data-image');
                const content = button.getAttribute('data-content');

                const modalTitle = announcementDetailModal.querySelector('#modalAnnouncementTitle');
                const modalDate = announcementDetailModal.querySelector('#modalAnnouncementDate');
                const modalCategory = announcementDetailModal.querySelector('#modalAnnouncementCategory');
                const modalImage = announcementDetailModal.querySelector('#modalAnnouncementImage');
                const modalContent = announcementDetailModal
                    .querySelector('#modalAnnouncementContent');

                modalTitle.textContent = title;
                modalDate.textContent = date;
                modalCategory.textContent = category;
                // Update category class based on fetched data
                modalCategory.className = `category-label category-${category.toLowerCase().replace(/\s+/g, '')}`;
                modalContent.textContent = content;

                if (image && image !== 'null' && image !== '') {
                    modalImage.src = image;
                    modalImage.classList.remove('d-none');
                } else {
                    modalImage.classList.add('d-none');
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
                    if (currentSearch) {
                        let searchInput = form.querySelector('input[name="search"]');
                        if (!searchInput) {
                            searchInput = document.createElement('input');
                            searchInput.type = 'hidden';
                            searchInput.name = 'search';
                            form.appendChild(searchInput);
                        }
                        searchInput.value = currentSearch;
                    }
                    form.submit();
                });
            }
        });
    </script>
</body>

</html>