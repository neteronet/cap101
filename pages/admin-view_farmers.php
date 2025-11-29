<?php
session_start();
// Ensure connection.php exists and returns a valid $conn object (mysqli connection)
include '../includes/connection.php';

// --- STABILITY CHECK 1: Ensure database connection is successful ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? "Connection object not set"));
    // Redirect or show a maintenance page, preventing the rest of the script from executing
    header("location: database_error.php"); // Assuming you have a file for connection errors
    exit();
}
// --- END STABILITY CHECK 1 ---

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: admin-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Admin'; // Default fallback

// Fetch admin's name
$stmt_name = $conn->prepare("SELECT name FROM users WHERE user_id = ? AND user_type = 'admin'");
if ($stmt_name) {
    $stmt_name->bind_param("i", $user_id);
    if ($stmt_name->execute()) { // Check execute success
        $stmt_name->bind_result($db_name);
        $stmt_name->fetch();
        if ($db_name) {
            $display_name = htmlspecialchars($db_name); // Sanitize immediately
        } else {
            // User not found or not admin, force logout
            session_destroy();
            header("location: admin-login.php");
            exit();
        }
    } else {
        error_log("Failed to execute statement for user name: " . $stmt_name->error);
    }
    $stmt_name->close();
} else {
    error_log("Failed to prepare statement for user name: " . $conn->error);
}

$farmers_users = [];
$message = '';
$message_type = '';

// ---------------------- PAGINATION SETUP ----------------------
$limit = 10; // Number of records per page
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;

// 1. Get total number of farmer users for pagination calculation
$total_records = 0;
$stmt_count = $conn->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'farmer'");
if ($stmt_count) {
    if ($stmt_count->execute()) {
        $stmt_count->bind_result($total_records);
        $stmt_count->fetch();
    } else {
        error_log("Error executing count statement: " . $stmt_count->error);
    }
    $stmt_count->close();
}

$total_pages = ceil($total_records / $limit);

// Ensure $page doesn't exceed $total_pages
if ($total_records > 0 && $page > $total_pages) {
    $page = $total_pages;
} elseif ($total_records == 0) {
    $page = 1; // If no records, set page to 1
    $total_pages = 1;
}

$offset = ($page - 1) * $limit;
// -------------------- END PAGINATION SETUP --------------------


// The registration logic block is removed as the form submission entry is not via this page anymore (it's handled by admin-register_farmer.php).
// The registration logic block was previously here (around lines 88-161).

// --- PHP DELETION LOGIC (Retained) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_farmer_submit'])) {
    $farmer_id_to_delete = $_POST['delete_farmer_id'] ?? null;

    if ($farmer_id_to_delete && is_numeric($farmer_id_to_delete)) {
        // Fetch the user_name for the success/error message before deletion
        $stmt_fetch_name = $conn->prepare("SELECT u.name FROM farmers f JOIN users u ON f.user_id = u.user_id WHERE f.farmer_id = ?");
        $user_name = 'a farmer';
        if ($stmt_fetch_name) {
            $stmt_fetch_name->bind_param("i", $farmer_id_to_delete);
            if ($stmt_fetch_name->execute()) {
                $stmt_fetch_name->bind_result($fetched_name);
                if ($stmt_fetch_name->fetch()) {
                    $user_name = htmlspecialchars($fetched_name);
                }
            } else {
                 error_log("Failed to execute name fetch for deletion: " . $stmt_fetch_name->error);
            }
            $stmt_fetch_name->close();
        } else {
            error_log("Failed to prepare name fetch for deletion: " . $conn->error);
        }


        // Delete the farmer's details (not the user account)
        $stmt_delete = $conn->prepare("DELETE FROM farmers WHERE farmer_id = ?");

        if ($stmt_delete) {
            $stmt_delete->bind_param("i", $farmer_id_to_delete);

            if ($stmt_delete->execute()) {
                $message = "Farmer details for {$user_name} (Farmer ID: {$farmer_id_to_delete}) deleted successfully! The user account remains.";
                $message_type = 'success';
            } else {
                $message = "Error deleting farmer details: " . $stmt_delete->error;
                $message_type = 'danger';
                error_log("Error deleting farmer details: " . $stmt_delete->error);
            }
            $stmt_delete->close();
        } else {
            $message = "Database error: Could not prepare farmer deletion statement.";
            $message_type = 'danger';
            error_log("Failed to prepare farmer deletion statement: " . $conn->error);
        }
    } else {
        $message = "Invalid Farmer ID provided for deletion.";
        $message_type = 'danger';
    }
    // Redirect to prevent form resubmission for delete and register actions
    header("Location: admin-view_farmers.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type) . "&p=" . $page);
    exit();
}
// --- END PHP DELETION LOGIC ---


// Fetch all users with user_type = 'farmer' and their registration status in the 'farmers' table,
// including all farmer details for the view modal, *WITH PAGINATION*
$stmt_farmers = $conn->prepare("
    SELECT
        u.user_id,
        u.name,
        u.username,
        u.created_at,
        f.farmer_id,
        f.rsbsa_id,
        f.first_name,
        f.middle_name,
        f.last_name,
        f.address,
        f.contact_number,
        f.land_details,
        f.age,
        f.gender,
        f.civil_status,
        f.crop,
        f.farmer_id IS NOT NULL AS is_registered_farmer
    FROM
        users u
    LEFT JOIN
        farmers f ON u.user_id = f.user_id
    WHERE
        u.user_type = 'farmer'
    ORDER BY
        u.created_at DESC
    LIMIT ? OFFSET ?
");

if ($stmt_farmers) {
    $stmt_farmers->bind_param("ii", $limit, $offset); // Bind limit and offset
    if ($stmt_farmers->execute()) {
        $result = $stmt_farmers->get_result();
        while ($row = $result->fetch_assoc()) {
            $farmers_users[] = $row;
        }
    } else {
        $message = "Error executing fetch farmers statement: " . $stmt_farmers->error;
        $message_type = 'danger';
        error_log("Error executing fetch farmers statement: " . $stmt_farmers->error);
    }
    $stmt_farmers->close();
} else {
    $message = "Error fetching farmers: " . $conn->error;
    $message_type = 'danger';
    error_log("Failed to prepare statement for fetching farmers: " . $conn->error);
}

// Check for messages from redirects
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}

// Ensure $conn is closed only if it was opened and not already closed
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin - View Farmers</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Custom Styles (Consistent with farmer-dashboard.php) -->
    <style>
        /* ... (CSS Styles remain the same) ... */
        body {
            font-family: "Poppins", sans-serif;
            background: #f8f9fa;
            font-size: 16px;
            line-height: 1.6;
            color: #212529;
            margin: 0;
        }

        /* --- Sidebar Styles (Consistent) --- */
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

        /* >>> INSERTION: Sidebar Menu Label Style <<< */
        .sidebar-menu-label {
            color: rgba(255, 255, 255, 0.7);
            padding: 0 1rem 0.5rem 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* >>> END INSERTION <<< */

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

        /* >>> MODIFIED: Header Brand (Logo and Text) <<< */
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

        /* >>> END MODIFIED <<< */

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

        /* --- Fixed Top Header (Consistent) --- */
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

        /* 5. Typography Consistency: Headings and Titles */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .card-title,
        .modal-title,
        .page-title {
            font-family: "Be Vietnam Pro", sans-serif;
            color: #0f5132;
        }

        /* NEW: Style for the Page Title (Consistent) */
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        /* NEW: Style for the Dashboard Description Paragraph (Consistent) */
        .dashboard-description {
            font-size: 0.875rem;
        }

        /* NEW: Explicit Card Title Size for Consistency */
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        /* NEW: Explicit Standard Card Text Size for Consistency */
        .card-text,
        .card-body p:not(.card-title),
        .list-unstyled li,
        .table {
            font-size: 0.9375rem;
        }

        /* 2. Button and Alert Unification: Button Theme */
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

        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            border: 1px solid #ddd;
        }

        /* Custom status badge classes */
        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.4rem;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        /* Re-mapping status badges to Bootstrap colors for theme consistency */
        .status-registered {
            background-color: #198754 !important;
            /* Success */
            color: #fff !important;
        }

        .status-not-registered {
            background-color: #ffc107 !important;
            /* Warning */
            color: #664d03 !important;
        }

        #sidebarToggleBtn {
            color: #0f5132;
        }

        #sidebarToggleBtn:hover {
            color: #146c0b;
        }

        /* Style for the new delete modal close button */
        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
    </style>
</head>

<body>
    <!-- Sidebar (Updated to new style) -->
    <nav class="sidebar">
        <!-- Logo and Text -->
        <a href="admin-dashboard.php" class="header-brand">
            <!-- Assuming 'logo.png' is the consistent logo image -->
            <img src="../photos/logo.png" alt="Department of Agriculture Logo" />
            <div>Agriconnect</div>
        </a>

        <!-- Menu Label -->
        <div class="sidebar-menu-label">Main Menu</div>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="admin-dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-add_user.php" class="nav-link">
                    <i class="fas fa-user-plus"></i> Add User
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-view_farmers.php" class="nav-link active">
                    <i class="fas fa-users"></i> View Farmers
                </a>
            </li>
        </ul>
        <!-- Logout Section (Moved from Header) -->
        <div class="sidebar-logout">
            <a href="admin-logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Header (Updated to new style, without bell) -->
    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
        <!-- Sidebar Toggle Button -->
        <button id="sidebarToggleBtn" class="btn btn-link p-0 text-dark" title="Toggle Sidebar" style="font-size: 1.5rem;">
            <i class="fas fa-bars"></i>
        </button>
        <!-- Right side alignment wrapper -->
        <div class="d-flex align-items-center">
            <!-- Greeting -->
            <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        </div>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h1 class="page-title">Farmer Management</h1>
            <p class="text-muted mb-4 dashboard-description">
                Overview and management of registered farmer users and their detailed profile information.
            </p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">List of Registered Farmers</h5>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <!-- Removed <th>#</th> -->
                                    <th>Full Name</th>
                                    <th>Username (Email)</th>
                                    <th>Date Registered (User)</th>
                                    <!-- REMOVED: <th>Status</th> -->
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($farmers_users)): ?>
                                    <tr>
                                        <!-- Adjusted colspan from 5 to 4 -->
                                        <td colspan="4" class="text-center">No farmers registered on this page.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($farmers_users as $farmer_user): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($farmer_user['name']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($farmer_user['username']); ?></td>
                                            <td><?php echo htmlspecialchars(date('M d, Y H:i A', strtotime($farmer_user['created_at']))); ?></td>
                                            <!-- REMOVED: Status <td> block -->
                                            <td>
                                                <?php if (!$farmer_user['is_registered_farmer']): ?>
                                                    <!-- Link for registration (Icon only) -->
                                                    <a href="admin-register_farmer.php?user_id=<?php echo $farmer_user['user_id']; ?>" class="btn btn-sm btn-theme" title="Register Farmer Details">
                                                        <i class="fas fa-plus"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <!-- Actions for Registered Farmers: View, Edit, and Delete (Icons only) -->
                                                    <div class="d-flex flex-wrap gap-1" role="group" aria-label="Farmer Actions">
                                                        <!-- NEW VIEW BUTTON: Carries the data attributes for the modal -->
                                                        <button type="button" class="btn btn-sm btn-info view-farmer-btn"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewFarmerDetailsModal"
                                                            data-farmer-id="<?php echo $farmer_user['farmer_id']; ?>"
                                                            data-user-name="<?php echo htmlspecialchars($farmer_user['name']); ?>"
                                                            data-rsbsa-id="<?php echo htmlspecialchars($farmer_user['rsbsa_id']); ?>"
                                                            data-first-name="<?php echo htmlspecialchars($farmer_user['first_name']); ?>"
                                                            data-middle-name="<?php echo htmlspecialchars($farmer_user['middle_name']); ?>"
                                                            data-last-name="<?php echo htmlspecialchars($farmer_user['last_name']); ?>"
                                                            data-address="<?php echo htmlspecialchars($farmer_user['address']); ?>"
                                                            data-contact-number="<?php echo htmlspecialchars($farmer_user['contact_number']); ?>"
                                                            data-land-details='<?php echo htmlspecialchars($farmer_user['land_details']); ?>'
                                                            data-age="<?php echo htmlspecialchars($farmer_user['age']); ?>"
                                                            data-gender="<?php echo htmlspecialchars($farmer_user['gender']); ?>"
                                                            data-civil-status="<?php echo htmlspecialchars($farmer_user['civil_status']); ?>"
                                                            data-crop="<?php echo htmlspecialchars($farmer_user['crop']); ?>"
                                                            onclick="event.stopPropagation();" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <!-- EDIT BUTTON -->
                                                        <a href="admin-edit_farmer.php?farmer_id=<?php echo $farmer_user['farmer_id']; ?>" class="btn btn-sm btn-warning" onclick="event.stopPropagation();" title="Edit Details">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <!-- DELETE BUTTON (Triggers Modal) -->
                                                        <button type="button" class="btn btn-sm btn-danger delete-farmer-btn"
                                                            data-bs-toggle="modal" data-bs-target="#deleteFarmerModal"
                                                            data-farmer-id="<?php echo $farmer_user['farmer_id']; ?>"
                                                            data-user-name="<?php echo htmlspecialchars($farmer_user['name']); ?>"
                                                            onclick="event.stopPropagation();" title="Delete Details">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-3">
                            <!-- Previous Button -->
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?p=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>

                            <?php
                            // Logic for displaying a reasonable number of page links (e.g., current +/- 2)
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?p=1">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?p=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>

                            <?php
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?p=' . $total_pages . '">' . $total_pages . '</a></li>';
                            }
                            ?>

                            <!-- Next Button -->
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?p=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    <!-- End Pagination Controls -->
                </div>
            </div>
        </div>
    </main>

    <!-- View Farmer Details Modal (Unchanged) -->
    <div class="modal fade" id="viewFarmerDetailsModal" tabindex="-1" aria-labelledby="viewFarmerDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewFarmerDetailsModalLabel">Farmer Details for <span id="viewModalUserName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <strong>RSBSA ID:</strong> <span id="viewRsbsaId"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Full Name:</strong> <span id="viewFullName"></span>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <strong>Address:</strong> <span id="viewAddress"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Contact Number:</strong> <span id="viewContactNumber"></span>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <strong>Age:</strong> <span id="viewAge"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Gender:</strong> <span id="viewGender"></span>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <strong>Civil Status:</strong> <span id="viewCivilStatus"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Main Crop:</strong> <span id="viewCrop"></span>
                        </div>
                    </div>
                    <h6 class="mt-3">Land Details:</h6>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <strong>Location:</strong> <span id="viewLandLocation"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Size:</strong> <span id="viewLandSize"></span>
                        </div>
                    </div>
                </div>
                <!-- MODIFIED MODAL FOOTER: Only Close button remains -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
                <!-- END MODIFIED MODAL FOOTER -->
            </div>
        </div>
    </div>

    <!-- Delete Farmer Confirmation Modal (Retained) -->
    <div class="modal fade" id="deleteFarmerModal" tabindex="-1" aria-labelledby="deleteFarmerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form action="admin-view_farmers.php" method="POST">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteFarmerModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the farmer details for <strong><span id="deleteModalUserName"></span></strong>?</p>
                        <p class="text-danger small">This action is irreversible and will remove all associated farmer data, but the user account will remain.</p>
                        <input type="hidden" name="delete_farmer_id" id="deleteModalFarmerId">
                        <input type="hidden" name="delete_farmer_submit" value="1">
                        <!-- Added hidden input for current page to redirect back to the correct view -->
                        <input type="hidden" name="p" value="<?php echo $page; ?>"> 
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // --- Sidebar Toggle JavaScript (Consistent with farmer-dashboard.php) ---
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

            // Toggle button functionality (now uses state saving)
            toggleBtn.addEventListener('click', function() {
                if (sidebar.classList.contains('collapsed')) {
                    openSidebar();
                } else {
                    collapseSidebar();
                }
            });
            // --- END Sidebar Toggle JavaScript ---

            // --- VIEW FARMER DETAILS MODAL LOGIC (Now triggered explicitly by the 'View' button) ---
            var viewFarmerDetailsModal = document.getElementById('viewFarmerDetailsModal');
            viewFarmerDetailsModal.addEventListener('show.bs.modal', function(event) {
                // Determine the trigger element (now expected to be the 'View' button).
                var button = event.relatedTarget;

                // We get the data attributes directly from the button
                var userName = button.getAttribute('data-user-name');
                var rsbsaId = button.getAttribute('data-rsbsa-id');
                var firstName = button.getAttribute('data-first-name');
                var middleName = button.getAttribute('data-middle-name');
                var lastName = button.getAttribute('data-last-name');
                var address = button.getAttribute('data-address');
                var contactNumber = button.getAttribute('data-contact-number');
                var landDetailsJson = button.getAttribute('data-land-details');
                var age = button.getAttribute('data-age');
                var gender = button.getAttribute('data-gender');
                var civilStatus = button.getAttribute('data-civil-status');
                var crop = button.getAttribute('data-crop');

                // Parse land details JSON
                var landLocation = 'N/A';
                var landSizeRaw = 'N/A';
                var landHectaresValue = 'N/A';

                try {
                    if (landDetailsJson) {
                        var landDetails = JSON.parse(landDetailsJson);
                        landLocation = landDetails.location || 'N/A';
                        landSizeRaw = landDetails.size || 'N/A';

                        if (landSizeRaw !== 'N/A') {
                            // Try to extract the numeric part
                            const match = landSizeRaw.match(/(\d+(\.\d+)?)/);
                            if (match && match[1]) {
                                landHectaresValue = match[1];
                            }
                        }
                    }
                } catch (e) {
                    console.error("Error parsing land_details JSON:", e);
                }

                // Update the modal's content
                viewFarmerDetailsModal.querySelector('#viewModalUserName').textContent = userName;
                viewFarmerDetailsModal.querySelector('#viewRsbsaId').textContent = rsbsaId;
                // Construct full name
                viewFarmerDetailsModal.querySelector('#viewFullName').textContent = `${firstName} ${middleName ? middleName + ' ' : ''}${lastName}`;
                viewFarmerDetailsModal.querySelector('#viewAddress').textContent = address;
                viewFarmerDetailsModal.querySelector('#viewContactNumber').textContent = contactNumber;
                viewFarmerDetailsModal.querySelector('#viewAge').textContent = age;
                viewFarmerDetailsModal.querySelector('#viewGender').textContent = gender;
                viewFarmerDetailsModal.querySelector('#viewCivilStatus').textContent = civilStatus;
                viewFarmerDetailsModal.querySelector('#viewCrop').textContent = crop;
                viewFarmerDetailsModal.querySelector('#viewLandLocation').textContent = landLocation;
                // Display size
                viewFarmerDetailsModal.querySelector('#viewLandSize').textContent = (landHectaresValue !== 'N/A' ? landHectaresValue : 'N/A') + (landHectaresValue !== 'N/A' ? ' hectares' : '');
            });


            // --- DELETE FARMER MODAL JAVASCRIPT ---
            var deleteFarmerModal = document.getElementById('deleteFarmerModal');
            deleteFarmerModal.addEventListener('show.bs.modal', function(event) {
                // Button that triggered the modal
                var button = event.relatedTarget;
                // Extract info from data-bs-* attributes
                var farmerId = button.getAttribute('data-farmer-id');
                var userName = button.getAttribute('data-user-name');

                // Update the modal's content.
                var modalUserNameSpan = deleteFarmerModal.querySelector('#deleteModalUserName');
                var modalFarmerIdInput = deleteFarmerModal.querySelector('#deleteModalFarmerId');

                modalUserNameSpan.textContent = userName;
                modalFarmerIdInput.value = farmerId;
            });
            // --- END DELETE FARMER MODAL JAVASCRIPT ---
        });
    </script>
</body>

</html>