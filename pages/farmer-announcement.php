<?php
session_start(); // Start the session at the very beginning of the script

// Check if the user is logged in. If not, redirect to the login page.
// Adjust 'farmers-login.php' to your actual login page filename and path if different.
if (!isset($_SESSION['user_id'])) {
    header("location: farmers-login.php");
    exit();
}

// Retrieve the user's name from the session.
$display_name = $_SESSION['name'] ?? 'Farmer'; // Fallback to 'Farmer' if not set

$servername = "localhost";
$db_username = "root"; // Your database username
$db_password = "";     // Your database password
$dbname = "cap101"; // Your database name

// Create database connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    // You might want to redirect to an error page or show a friendly message
    die("Connection failed: " . $conn->connect_error); // For debugging, remove in production
} else {
    // Fetch user's name from DB if available
    $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($fetched_db_name);
    $stmt->fetch();
    if ($fetched_db_name) {
        $display_name = $fetched_db_name; // Use the name fetched from DB
    }
    $stmt->close();
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

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Custom Styles -->
    <style>
        body {
            font-family: "Poppins", sans-serif;
            background: #f8f9fa;
            font-size: 16px; /* Matched to dashboard */
            line-height: 1.6; /* Matched to dashboard */
            color: #333; /* Matched to dashboard */
            margin: 0;
        }

        /* Sidebar - Matched to dashboard */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: #19860f;
            padding: 1rem 0;
            overflow-y: auto;
            font-size: 14px; /* Matched to dashboard */
            z-index: 1050;
            border-right: 1px solid #ddd;
        }

        .sidebar .nav-link {
            color: #fff;
            padding: 0.6rem 1rem; /* Matched to dashboard */
            width: 100%;
            box-sizing: border-box;
            border-radius: 0;
            display: flex;
            align-items: center;
            text-decoration: none;
        }


        .sidebar .nav-link i {
            margin-right: 8px; /* Matched to dashboard */
            font-size: 1rem; /* Matched to dashboard */
        }

        .sidebar .nav-link.active {
            background-color: #fff;
            color: #19860f;
            font-weight: 600;
        }

        .sidebar .nav-link:hover:not(.active) {
            background-color: #146c0b; /* Matched to dashboard */
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
            max-width: 120px; /* Matched to dashboard */
            height: auto;
            background: #19860f;
            padding: 5px;
            border-radius: 4px;
        }

        .sidebar .header-brand div {
            font-size: 14px; /* Matched to dashboard */
            font-weight: 600;
            color: #fff;
            text-align: center;
            margin-top: 6px;
        }

        /* Header - Matched to dashboard */
        .card-header-custom {
            position: fixed;
            top: 0;
            left: 250px;
            right: 0;
            height: 56px; /* Matched to dashboard */
            background-color: #fff;
            color: #19860f;
            padding: 0 1.25rem; /* Matched to dashboard */
            font-weight: 500;
            font-size: 1rem; /* Matched to dashboard */
            display: flex;
            align-items: center;
            justify-content: flex-end; /* Adjusted for logout button alignment */
            z-index: 1060;
            border-bottom: 1px solid #ddd;
        }

        .header-brand span { /* This style is for the "Hi, username" in the top header, which is now green */
            font-size: 1rem;
            font-weight: 600;
            color: #19860f;
        }

        /* Logout Button - Matched to dashboard */
        .logout-btn {
            background: #ff4b2b;
            color: #fff;
            border: none;
            padding: 6px 14px; /* Matched to dashboard */
            font-size: 14px; /* Matched to dashboard */
            border-radius: 20px; /* Matched to dashboard */
            transition: background 0.2s ease;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: #e04325;
        }

        /* Themed Buttons - Matched to dashboard */
        .btn-theme {
            background-color: #19860f;
            color: #fff;
            font-size: 15px; /* Matched to dashboard */
            padding: 10px 20px; /* Matched to dashboard */
            border-radius: 4px; /* Matched to dashboard */
            border: none; /* Ensure no default border */
            transition: all 0.3s ease; /* Added transition */
        }

        .btn-theme:hover {
            background-color: #146c0b;
            color: #fff; /* Ensure text color remains white */
            transform: translateY(-1px); /* Slight lift on hover */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Soft shadow on hover */
        }

        main {
            margin-left: 250px; /* Matched sidebar width */
            padding: 1rem 2rem 2rem 2rem; /* Matched to dashboard */
            padding-top: 72px; /* Adjusted for fixed header height */
            background: #f8f9fa;
            min-height: 100vh;
        }

        .page-title {
            font-size: 1.8rem; /* Matched to dashboard */
            font-weight: 600; /* Matched to dashboard */
            color: #19860f;
            margin-bottom: 1rem; /* Matched to dashboard */
        }

        .card {
            border-radius: 0.5rem; /* Matched to dashboard */
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05); /* Matched to dashboard */
            margin-bottom: 1rem; /* Matched to dashboard */
        }

        .card-title {
            color: #19860f;
            font-size: 1.25rem; /* Matched to dashboard */
            margin-bottom: 0.75rem;
        }

        .status-badge { /* Although not directly used for announcements, kept for consistency */
            padding: 0.3em 0.6em; /* Matched to dashboard */
            border-radius: 0.4rem; /* Matched to dashboard */
            font-size: 13px; /* Matched to dashboard */
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

        /* Announcement Specific Styles (Adjusted for consistency) */
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
            font-size: 0.85rem;
            color: #6c757d;
        }

        .announcement-category {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.2em 0.6em;
            border-radius: 0.3rem;
            margin-left: 10px;
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
            height: calc(1.5em + 1rem + 2px); /* Standard Bootstrap input height (form-control-lg) */
            font-size: 1rem; /* Adjusted for consistency */
            padding: 0.5rem 1rem; /* Adjusted for consistency */
            border-radius: 0.25rem; /* Standard Bootstrap radius */
        }
        .input-group .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .input-group .btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        .form-select.btn-theme { /* Styling for the dropdown filter */
            height: calc(1.5em + 1rem + 2px); /* Match input group height */
            font-size: 1rem;
            padding: 0.5rem 1rem 0.5rem 0.75rem; /* Adjust padding for dropdown arrow */
            border: 1px solid #19860f; /* Add border for consistency */
            background-color: #19860f;
            color: #fff;
            border-radius: 0.25rem;
            appearance: none; /* Remove default arrow */
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
        }
        .form-select.btn-theme:hover {
            background-color: #146c0b;
            border-color: #146c0b;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <a href="ProvincialAgriHome.html" class="header-brand">
            <img src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Province of Antique" />
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
                <a href="farmer-announcement.php" class="nav-link active">
                    <i class="fas fa-bullhorn"></i> Announcements
                </a>
            </li>
            <li class="nav-item">
                <a href="farmer-apply_for_assistance.php" class="nav-link">
                    <i class="fas fa-file-invoice"></i> Apply for Assistance
                </a>
            </li>
            <li class="nav-item">
                <a href="#cropMonitoringSubmenu" data-bs-toggle="collapse" class="nav-link d-flex justify-content-between align-items-center">
                    <div><i class="fas fa-seedling"></i> Crop Monitoring</div>
                    <i class="fas fa-chevron-down fa-xs"></i>
                </a>
                <div class="collapse" id="cropMonitoringSubmenu">
                    <ul class="nav flex-column ms-3">
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
                    </ul>
                </div>
            </li>
        </ul>
    </nav>

    <!-- Header -->
    <div class="card-header card-header-custom">
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <button class="logout-btn" onclick="location.href='farmers-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <h1 class="page-title"><i class="fas fa-bullhorn me-2"></i>Announcements & Updates</h1>
        <p class="text-muted mb-4">
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
                    <div class="alert alert-info" role="alert">
                        No announcements available at the moment. Please check back later!
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

            // Filtering and Searching Functionality
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
        });
    </script>
</body>
</html>