<?php
session_start(); // Start the session at the very beginning of the script

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

// --- Fetch Announcements from the Database ---
$announcements = []; // Initialize an empty array to store announcements

// Assuming your table name for announcements is 'announcements'
$sql = "SELECT id, title, category, content, image_url, publish_date FROM announcements ORDER BY publish_date DESC";
$result = $conn->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
    }
    $result->free(); // Free result set
} else {
    error_log("Error fetching announcements: " . $conn->error);
}

// Close the database connection after all operations
$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Farmer Account - Announcements</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts (UPDATED FOR CONSISTENCY) -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Custom Styles (MODIFIED FOR CONSISTENCY) -->
    <style>
        body {
            font-family: "Poppins", sans-serif;
            background: #f8f9fa;
            font-size: 16px;
            line-height: 1.6;
            color: #212529; /* Consistent dark text color */
            margin: 0;
        }

        /* --- Sidebar Styles (Consistent Design) --- */
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
            /* CONSISTENCY */
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .sidebar.collapsed {
            left: -250px;
        }

        .sidebar-menu-label {
            color: rgba(255, 255, 255, 0.7); /* Slightly transparent white */
            padding: 0 1rem 0.5rem 1rem; /* Padding to align with links */
            font-size: 0.75rem; /* Small text */
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

        /* MODIFIED: Header Brand (Logo and Text) */
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

        /* --- Fixed Top Header (Consistent Design) --- */
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
            justify-content: space-between; /* To put toggle on left and text on right */
            z-index: 1060;
            border-bottom: 1px solid #ddd;
            transition: left 0.3s ease;
            /* CONSISTENCY */
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .card-header-custom.collapsed {
            left: 0;
        }

        #sidebarToggleBtn {
            color: #0f5132;
        }

        #sidebarToggleBtn:hover {
            color: #146c0b;
        }

        /* --- Typography Consistency --- */
        h1, h2, h3, h4, h5, h6, .card-title, .modal-title, .page-title {
            /* CONSISTENCY: Set headings/titles font */
            font-family: "Be Vietnam Pro", sans-serif;
            color: #0f5132; /* Dark Green */
        }

        /* CONSISTENCY: Page Title Size */
        .page-title {
            font-size: 1.5rem; /* Reduced from 1.8rem for consistency */
            font-weight: 600;
            color: #0f5132; 
            margin-bottom: 0.5rem; /* Reduced for consistency */
        }
        
        /* CONSISTENCY: Card Title Size */
        .card-title {
            font-size: 1.25rem;
            font-weight: 600; /* Added slight boldness for consistency */
            margin-bottom: 0.75rem;
        }
        
        /* CONSISTENCY: Standard Card Text Size */
        .card-text, .card-body p:not(.card-title), .list-unstyled li {
            font-size: 0.9375rem; /* ~15px for consistency */
        }
        .card-text.small, .list-unstyled.small li, .card-text.text-muted.small {
            font-size: 0.875rem !important; /* Keep 14px for small elements */
        }
        
        /* Themed Buttons - Matched to dashboard */
        .btn-theme {
            background-color: #19860f;
            color: #fff;
            font-size: 15px;
            padding: 10px 20px;
            border-radius: 4px;
            border: none;
            transition: all 0.3s ease;
            /* CONSISTENCY */
            font-family: "Be Vietnam Pro", sans-serif;
            border-color: #19860f;
        }

        .btn-theme:hover {
            background-color: #146c0b;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            border-color: #146c0b;
        }

        main {
            margin-left: 250px; /* Matched sidebar width */
            padding: 72px 2rem 2rem 2rem; /* Adjusted padding-top for fixed header height */
            background: #f8f9fa;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        main.collapsed {
            margin-left: 0;
        }

        .card {
            border-radius: 0.5rem; /* Matched to dashboard */
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05); /* Matched to dashboard */
            margin-bottom: 1rem; /* Matched to dashboard */
            border: 1px solid #ddd; /* Matched to dashboard */
        }

        /* Announcement Specific Styles (Kept as is, inheriting new typography) */
        .announcement-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            border-left: 5px solid transparent;
        }

        .announcement-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }

        /* Dynamic border colors based on category */
        .announcement-card.type-advisory { border-left-color: #ffc107; } /* Warning yellow */
        .announcement-card.type-program { border-left-color: #19860f; } /* Primary green */
        .announcement-card.type-alert { border-left-color: #dc3545; } /* Danger red */
        .announcement-card.type-general { border-left-color: #0d6efd; } /* Info blue */
        /* If category is not one of above, no specific border will be applied */


        .announcement-date {
            font-size: 0.875rem; /* Consistent small font size */
            color: #6c757d;
        }

        .announcement-category {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.2em 0.6em;
            border-radius: 0.4rem; /* Slightly larger border-radius for badge consistency */
            margin-left: 10px;
            /* CONSISTENCY: Explicitly set font for badges */
            font-family: "Be Vietnam Pro", sans-serif;
        }
        /* Dynamic background colors for category badges */
        .category-advisory { background-color: #fff3cd; color: #664d03; }
        .category-program { background-color: #e6f2e6; color: #19860f; }
        .category-alert { background-color: #f8d7da; color: #842029; }
        .category-general { background-color: #cfe2ff; color: #052c65; }
        /* Fallback if category doesn't match a specific style */
        .announcement-category:not(.category-advisory):not(.category-program):not(.category-alert):not(.category-general) {
            background-color: #6c757d; /* grey */
            color: #fff;
        }


        #announcementDetailModal .modal-title {
            color: #19860f;
            font-weight: 600;
        }
        #announcementDetailModal .modal-body img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin-top: 15px;
            margin-bottom: 15px;
        }

        /* Adjust input group for consistent height */
        .input-group .form-control,
        .input-group .btn {
            /* Matched height calculation from dashboard styles */
            height: calc(1.5em + 1rem + 2px); 
            font-size: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
        }
        .input-group .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .input-group .btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            /* Ensures button is Be Vietnam Pro */
            font-family: "Be Vietnam Pro", sans-serif; 
        }
        .form-select.btn-theme {
            /* Matched height calculation from dashboard styles */
            height: calc(1.5em + 1rem + 2px); 
            font-size: 1rem;
            padding: 0.5rem 1rem 0.5rem 0.75rem;
            border: 1px solid #19860f;
            background-color: #19860f;
            color: #fff;
            border-radius: 0.25rem;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            /* CONSISTENCY */
            font-family: "Be Vietnam Pro", sans-serif; 
        }
        .form-select.btn-theme:hover {
            background-color: #146c0b;
            border-color: #146c0b;
        }
        /* Dashboard-like helper text */
        .dashboard-description {
            font-size: 0.875rem; /* 14px */
        }
    </style>
</head>
<body>
    <!-- Sidebar (Consistent Design) -->
    <nav class="sidebar">
        <a href="ProvincialAgriHome.html" class="header-brand">
            <!-- Changed logo source and text to match the reference dashboard -->
            <img src="../photos/logo.png" alt="Department of Agriculture Logo" />
            <div>Agriconnect</div>
        </a>

        <div class="sidebar-menu-label">Main Menu</div>

        <ul class="nav flex-column">
            <li class="nav-item"><a href="farmer-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="farmer-apply_for_assistance.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i>Apply for Assistance</a></li>
            <li class="nav-item"><a href="farmer-planting_status.php" class="nav-link"><i class="fas fa-leaf"></i> Planting Status</a></li>
            <li class="nav-item"><a href="farmer-claim_history.php" class="nav-link"><i class="fas fa-history"></i> Claim History</a></li>
            <li class="nav-item"><a href="farmer-announcement.php" class="nav-link active"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li class="nav-item"><a href="farmer-my_profile.php" class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a></li>
        </ul>

        <div class="sidebar-logout">
            <a href="farmers-logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Header (Consistent Design) -->
    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
        <button id="sidebarToggleBtn" class="btn btn-link p-0 text-dark" title="Toggle Sidebar" style="font-size: 1.5rem;">
            <i class="fas fa-bars"></i>
        </button>
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
    </div>

    <!-- Main Content -->
    <main>
        <h1 class="page-title"><i></i>Announcements & Updates</h1>
        <p class="text-muted mb-4 dashboard-description">
            Stay informed with the latest news, advisories, and program updates from the Provincial Agriculture Office.
        </p>

        <div class="row mb-4">
            <div class="col-md-8">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search announcements..." id="announcementSearch" />
                    <button class="btn btn-theme" type="button"><i class="fas fa-search"></i> Search</button>
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select btn-theme" id="announcementFilter">
                    <option value="all">All Categories</option>
                    <option value="advisory">Advisories</option>
                    <option value="program">Programs</option>
                    <option value="alert">Alerts</option>
                    <option value="general">General Updates</option>
                    <!-- Add more options if you have more distinct categories -->
                </select>
            </div>
        </div>

        <div class="row" id="announcementList">
            <?php if (empty($announcements)): ?>
                <div class="col-12">
                    <div class="card p-4">
                        <div class="alert alert-info mb-0" role="alert">
                            No announcements available at the moment. Please check back later!
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $announcement):
                    // Format the date for display
                    $formatted_date = date("F d, Y", strtotime($announcement['publish_date']));
                    // Sanitize category for class names (lowercase and remove spaces if necessary)
                    $category_slug = strtolower(str_replace(' ', '', $announcement['category']));
                ?>
                    <div class="col-md-6 col-lg-4 announcement-item type-<?php echo htmlspecialchars($category_slug); ?>">
                        <div class="card announcement-card h-100" data-bs-toggle="modal" data-bs-target="#announcementDetailModal"
                            data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                            data-date="<?php echo htmlspecialchars($formatted_date); ?>"
                            data-category="<?php echo htmlspecialchars($announcement['category']); ?>"
                            data-image="<?php echo htmlspecialchars($announcement['image_url']); ?>"
                            data-content="<?php echo htmlspecialchars($announcement['content']); ?>"
                        >
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="card-subtitle announcement-date"><?php echo htmlspecialchars($formatted_date); ?></h6>
                                    <span class="announcement-category category-<?php echo htmlspecialchars($category_slug); ?>"><?php echo htmlspecialchars($announcement['category']); ?></span>
                                </div>
                                <h5 class="card-title"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                <p class="card-text text-muted small">
                                    <?php
                                        // Display a truncated version of the content
                                        $short_content = substr($announcement['content'], 0, 100);
                                        if (strlen($announcement['content']) > 100) {
                                            $short_content .= '...';
                                        }
                                        echo htmlspecialchars($short_content);
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
                        <span id="modalAnnouncementCategory" class="announcement-category"></span>
                    </p>
                    <img id="modalAnnouncementImage" src="" alt="Announcement Image" class="img-fluid mb-3 d-none">
                    <p id="modalAnnouncementContent"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // --- Announcement Modal Logic (Existing) ---
            const announcementDetailModal = document.getElementById('announcementDetailModal');
            announcementDetailModal.addEventListener('show.bs.modal', function (event) {
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
                const modalContent = announcementDetailModal.querySelector('#modalAnnouncementContent');

                modalTitle.textContent = title;
                modalDate.textContent = date;
                // Update category text
                modalCategory.textContent = category;
                // Update category class for styling
                const categorySlug = category.toLowerCase().replace(/\s/g, ''); // Convert 'General Updates' to 'generalupdates'
                modalCategory.className = `announcement-category category-${categorySlug}`;
                modalContent.textContent = content;

                if (image && image !== 'null' && image !== '') { // Check if image_url is not empty or 'null' string
                    modalImage.src = image;
                    modalImage.classList.remove('d-none');
                } else {
                    modalImage.classList.add('d-none');
                    modalImage.src = ''; // Clear src to prevent broken image icon
                }
            });

            // --- Filtering and Searching Functionality (Existing) ---
            const announcementSearch = document.getElementById('announcementSearch');
            const announcementFilter = document.getElementById('announcementFilter');
            const announcementList = document.getElementById('announcementList');
            // Get all announcement items dynamically after they are loaded
            let announcementItems = announcementList.querySelectorAll('.announcement-item');

            function filterAnnouncements() {
                const searchTerm = announcementSearch.value.toLowerCase();
                const filterCategory = announcementFilter.value.toLowerCase(); // Ensure lowercase for comparison

                announcementItems.forEach(item => {
                    const title = item.querySelector('.card-title').textContent.toLowerCase();
                    const shortContent = item.querySelector('.card-text').textContent.toLowerCase(); // Search in short content too
                    // Get the category from the span's text content, then slugify it for comparison
                    const itemCategorySpan = item.querySelector('.announcement-category').textContent;
                    const itemCategorySlug = itemCategorySpan.toLowerCase().replace(/\s/g, '');


                    const matchesSearch = title.includes(searchTerm) || shortContent.includes(searchTerm);
                    const matchesCategory = itemCategorySlug.includes(filterCategory) || filterCategory === 'all';


                    if (matchesSearch && matchesCategory) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }

            announcementSearch.addEventListener('keyup', filterAnnouncements);
            announcementFilter.addEventListener('change', filterAnnouncements);

            // Initial filter call to ensure correct display if a filter is pre-selected or search bar is not empty
            filterAnnouncements();

            // --- Sidebar Toggle Logic (CONSISTENT DESIGN) ---
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
        });
    </script>
</body>
</html>