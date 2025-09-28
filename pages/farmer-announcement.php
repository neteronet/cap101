<?php
session_start(); // Start the session at the very beginning of the script

// Check if the user is logged in. If not, redirect to the login page.
// Adjust 'farmers-login.php' to your actual login page filename and path if different.
if (!isset($_SESSION['user_id'])) {
    header("location: farmers-login.php");
    exit();
}

// Retrieve the user's name from the session.
// In your login example, you stored 'username' (e.g., 'delacruzjuan') in the session.
// We'll use this for the display.
$display_name = $_SESSION['name'] ?? 'Farmer'; // Fallback to 'Farmer' if not set

// If you had a 'full_name' column in your database and stored it in the session,
// you would use that instead. For example, if you stored $_SESSION['full_name']
// $display_name = $_SESSION['full_name'] ?? 'Farmer';

$servername = "localhost";
$db_username = "root"; // Your database username
$db_password = "";     // Your database password
$dbname = "cap101"; // Your database name

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    // Log error or display a generic message, but don't expose database details
    error_log("Database connection failed: " . $conn->connect_error);
    // You might want to redirect to an error page or show a friendly message
} else {
    // Assuming your 'users' table has a 'username' column that serves as the display name
    // If you have a 'first_name' and 'last_name', you'd fetch those.
    $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($fetched_db_name);
    $stmt->fetch();
    if ($fetched_db_name) {
        $display_name = $fetched_db_name; // Use the name fetched from DB
    }
    $stmt->close();
    $conn->close();
}

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

        .announcement-card.type-advisory {
            border-left-color: #ffc107; /* Warning yellow */
        }
        .announcement-card.type-program {
            border-left-color: #19860f; /* Primary green */
        }
        .announcement-card.type-alert {
            border-left-color: #dc3545; /* Danger red */
        }
        .announcement-card.type-general {
            border-left-color: #0d6efd; /* Info blue */
        }

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
        .category-advisory { background-color: #fff3cd; color: #664d03; }
        .category-program { background-color: #e6f2e6; color: #19860f; }
        .category-alert { background-color: #f8d7da; color: #842029; }
        .category-general { background-color: #cfe2ff; color: #052c65; }

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
                </select>
            </div>
        </div>

        <div class="row" id="announcementList">
            <!-- Announcement Card Example 1: Advisory -->
            <div class="col-md-6 col-lg-4 announcement-item type-advisory">
                <div class="card announcement-card h-100" data-bs-toggle="modal" data-bs-target="#announcementDetailModal"
                    data-title="Advisory: Managing Fall Armyworm Infestation"
                    data-date="May 15, 2024"
                    data-category="Advisory"
                    data-image="https://via.placeholder.com/600x400/ffc107/ffffff?text=Fall+Armyworm"
                    data-content="Farmers are advised to take immediate action against the potential threat of Fall Armyworm (FAW) infestation. FAW can cause significant damage to corn and other crops. Early detection and proper management strategies are crucial. Please refer to the attached detailed guide for identification and control methods. Contact your local agricultural officer for assistance."
                >
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="card-subtitle announcement-date">May 15, 2024</h6>
                            <span class="announcement-category category-advisory">Advisory</span>
                        </div>
                        <h5 class="card-title">Managing Fall Armyworm Infestation</h5>
                        <p class="card-text text-muted small">
                            Immediate actions to protect your crops from Fall Armyworm.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Announcement Card Example 2: Program Update -->
            <div class="col-md-6 col-lg-4 announcement-item type-program">
                <div class="card announcement-card h-100" data-bs-toggle="modal" data-bs-target="#announcementDetailModal"
                    data-title="New Rice Seed Distribution Program"
                    data-date="May 10, 2024"
                    data-category="Program"
                    data-image="https://via.placeholder.com/600x400/19860f/ffffff?text=Rice+Program"
                    data-content="The Provincial Agriculture Office is launching a new rice seed distribution program for the wet season planting. Registered farmers are encouraged to apply for high-yielding rice varieties. Eligibility criteria and application procedures are available online and at your local MAO office. Deadline for applications is June 15, 2024."
                >
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="card-subtitle announcement-date">May 10, 2024</h6>
                            <span class="announcement-category category-program">Program</span>
                        </div>
                        <h5 class="card-title">New Rice Seed Distribution Program</h5>
                        <p class="card-text text-muted small">
                            Details on the upcoming rice seed distribution for the wet season.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Announcement Card Example 3: Disaster Alert -->
            <div class="col-md-6 col-lg-4 announcement-item type-alert">
                <div class="card announcement-card h-100" data-bs-toggle="modal" data-bs-target="#announcementDetailModal"
                    data-title="TYPHOON ALERT: Prepare for 'Bagyong Lando'"
                    data-date="May 08, 2024"
                    data-category="Alert"
                    data-image="https://via.placeholder.com/600x400/dc3545/ffffff?text=Typhoon+Alert"
                    data-content="A strong typhoon, 'Bagyong Lando', is expected to make landfall in the province within the next 48 hours. Farmers are urged to secure their farms, harvest mature crops if possible, and ensure the safety of livestock. Follow local government advisories for evacuation plans. Emergency hotlines are available."
                >
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="card-subtitle announcement-date">May 08, 2024</h6>
                            <span class="announcement-category category-alert">Alert</span>
                        </div>
                        <h5 class="card-title">TYPHOON ALERT: Prepare for 'Bagyong Lando'</h5>
                        <p class="card-text text-muted small">
                            Urgent advisory regarding an approaching strong typhoon.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Announcement Card Example 4: General Update -->
            <div class="col-md-6 col-lg-4 announcement-item type-general">
                <div class="card announcement-card h-100" data-bs-toggle="modal" data-bs-target="#announcementDetailModal"
                    data-title="Agricultural Census 2024 Extension"
                    data-date="May 01, 2024"
                    data-category="General"
                    data-image="https://via.placeholder.com/600x400/0d6efd/ffffff?text=Census+Extension"
                    data-content="The deadline for the 2024 Agricultural Census has been extended until May 31, 2024. All farmers who have not yet participated are requested to complete the survey as soon as possible. Your cooperation is vital for accurate data collection and effective policy-making. Visit your barangay hall or MAO for assistance."
                >
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="card-subtitle announcement-date">May 01, 2024</h6>
                            <span class="announcement-category category-general">General</span>
                        </div>
                        <h5 class="card-title">Agricultural Census 2024 Extension</h5>
                        <p class="card-text text-muted small">
                            Extended deadline for the vital Agricultural Census.
                        </p>
                    </div>
                </div>
            </div>

            <!-- More announcements can be added here -->
            <div class="col-md-6 col-lg-4 announcement-item type-program">
                <div class="card announcement-card h-100" data-bs-toggle="modal" data-bs-target="#announcementDetailModal"
                    data-title="Organic Farming Workshop Schedule"
                    data-date="April 28, 2024"
                    data-category="Program"
                    data-image="https://via.placeholder.com/600x400/19860f/ffffff?text=Organic+Workshop"
                    data-content="Learn sustainable practices at our upcoming organic farming workshops. Sessions will cover natural pest control, composting, and soil health. Open to all interested farmers. Registration is required due to limited slots. See the full schedule and topics at the MAO office or online portal."
                >
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="card-subtitle announcement-date">April 28, 2024</h6>
                            <span class="announcement-category category-program">Program</span>
                        </div>
                        <h5 class="card-title">Organic Farming Workshop Schedule</h5>
                        <p class="card-text text-muted small">
                            Enroll now for free workshops on sustainable agriculture.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4 announcement-item type-advisory">
                <div class="card announcement-card h-100" data-bs-toggle="modal" data-bs-target="#announcementDetailModal"
                    data-title="Water Conservation Tips for Dry Season"
                    data-date="April 20, 2024"
                    data-category="Advisory"
                    data-image="https://via.placeholder.com/600x400/ffc107/ffffff?text=Water+Conservation"
                    data-content="With the dry season in full swing, it's crucial to practice water conservation in farming. This advisory provides practical tips for efficient irrigation, mulching, and selecting drought-resistant crops to maximize water use and protect your yield. Learn more about effective water management."
                >
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="card-subtitle announcement-date">April 20, 2024</h6>
                            <span class="announcement-category category-advisory">Advisory</span>
                        </div>
                        <h5 class="card-title">Water Conservation Tips for Dry Season</h5>
                        <p class="card-text text-muted small">
                            Strategies for efficient water usage during the dry months.
                        </p>
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
                modalCategory.textContent = category;
                modalCategory.className = `announcement-category category-${category.toLowerCase()}`;
                modalContent.textContent = content;

                if (image) {
                    modalImage.src = image;
                    modalImage.classList.remove('d-none');
                } else {
                    modalImage.classList.add('d-none');
                }
            });

            // Filtering and Searching Functionality
            const announcementSearch = document.getElementById('announcementSearch');
            const announcementFilter = document.getElementById('announcementFilter');
            const announcementList = document.getElementById('announcementList');
            const announcementItems = announcementList.querySelectorAll('.announcement-item');

            function filterAnnouncements() {
                const searchTerm = announcementSearch.value.toLowerCase();
                const filterCategory = announcementFilter.value;

                announcementItems.forEach(item => {
                    const title = item.querySelector('.card-title').textContent.toLowerCase();
                    const textContent = item.textContent.toLowerCase();
                    const itemCategory = item.classList.contains(`type-${filterCategory}`) || filterCategory === 'all';

                    const matchesSearch = title.includes(searchTerm) || textContent.includes(searchTerm);

                    if (matchesSearch && itemCategory) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }

            announcementSearch.addEventListener('keyup', filterAnnouncements);
            announcementFilter.addEventListener('change', filterAnnouncements);
        });
    </script>
</body>
</html>