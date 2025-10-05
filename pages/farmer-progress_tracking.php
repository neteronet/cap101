<?php
session_start(); // Start the session at the very beginning of the script

include '../includes/connection.php';

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: farmers-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Farmer'; // Default fallback

// --- IMPROVED NAME FETCHING ---
// Always try to fetch the name from the database for accuracy.
// This ensures that if the session name is outdated or not set, the DB name is used.
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


// --- Fetch User's Current Planting Statuses for Progress Tracking ---
$user_tracked_crops = [];
$stmt = $conn->prepare("SELECT id, crop_identifier, status, photo_path, update_date FROM planting_status WHERE user_id = ? ORDER BY update_date DESC");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $user_tracked_crops[] = $row;
    }
    $stmt->close();
} else {
    error_log("Failed to prepare statement for fetching user tracked crops: " . $conn->error);
}

$conn->close(); // Close the connection after all database operations

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Account - Progress Tracking</title>
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

        /* --- Sidebar Styles (from dashboard) --- */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: #19860f;
            /* Main green */
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
            transition: all 0.2s ease-in-out;
        }

        .sidebar .nav-link i {
            margin-right: 8px;
            font-size: 1rem;
        }

        .sidebar .nav-link.active {
            background-color: #fff;
            /* Active link background */
            color: #19860f;
            /* Active link text color */
            font-weight: 600;
        }

        .sidebar .nav-link:hover:not(.active) {
            background-color: #146c0b;
            /* Darker green on hover */
            color: #fff;
        }


        .sidebar .header-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            margin-bottom: 1rem;
            padding: 0 1rem;
            /* Padding for the brand area */
        }

        .sidebar .header-brand img {
            width: 100%;
            max-width: 120px;
            height: auto;
            background: #19860f;
            /* Match sidebar background */
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

        /* --- Fixed Top Header (from dashboard) --- */
        .card-header-custom {
            position: fixed;
            top: 0;
            left: 250px;
            /* Aligned with main content start */
            right: 0;
            height: 56px;
            /* Standard Bootstrap navbar height */
            background-color: #fff;
            color: #19860f;
            /* Green text for branding/user info */
            padding: 0 1.25rem;
            font-weight: 500;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            /* Align items to the right */
            z-index: 1060;
            /* Higher than sidebar */
            border-bottom: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            /* Subtle shadow */
        }

        .header-brand span {
            /* This style is for the "AntiqueProv Agri" in the original header. Not used in this layout. */
            font-size: 1rem;
            font-weight: 600;
            color: #19860f;
        }

        .logout-btn {
            background: #ff4b2b;
            /* Red */
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
            /* Darker red on hover */
        }

        /* --- Main Content Area --- */
        main {
            margin-left: 250px;
            /* Space for the sidebar */
            padding: 1rem 2rem 2rem 2rem;
            padding-top: 72px;
            /* Space for the fixed top header */
            background: #f8f9fa;
            min-height: 100vh;
        }

        .page-title {
            font-size: 1.8rem;
            /* Adjusted for consistency */
            font-weight: 600;
            color: #19860f;
            /* Green */
            margin-bottom: 1rem;
        }

        .card {
            border-radius: 0.5rem;
            /* Consistent border-radius */
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            /* Consistent shadow */
            margin-bottom: 1rem;
        }

        .card-title {
            color: #19860f;
            /* Green title for cards */
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 1.25rem;
            /* Consistent with dashboard */
        }

        /* --- Progress Tracking Specific Styles --- */
        .progress-bar-custom {
            background-color: #28a745;
            /* Success green */
        }

        .progress-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .progress-text {
            font-size: 0.9em;
            color: #6c757d;
        }

        .btn-theme {
            /* Re-using the btn-theme from dashboard for consistency */
            background-color: #19860f;
            color: #fff;
            font-size: 15px;
            padding: 10px 20px;
            border-radius: 4px;
            transition: background 0.2s ease;
            border: none;
            /* Ensure no default border */
        }

        .btn-theme:hover {
            background-color: #146c0b;
            color: #fff;
            /* Keep text white on hover */
        }

        .btn-outline-info {
            color: #17a2b8;
            border-color: #17a2b8;
            transition: all 0.2s ease;
        }

        .btn-outline-info:hover {
            background-color: #17a2b8;
            color: #fff;
        }

        .btn-outline-primary {
            color: #007bff;
            border-color: #007bff;
            transition: all 0.2s ease;
        }

        .btn-outline-primary:hover {
            background-color: #007bff;
            color: #fff;
        }

        /* --- Image View Modal Adjustments --- */
        .modal-dialog {
            margin-top: 60px;
            max-width: 700px; /* Reduced max-width for a slightly smaller feel, adjust as needed */
            /* Optional: Add max-height and overflow if you want to strictly control dialog height */
            /* max-height: 80vh; */
            /* overflow-y: auto; */
        }

        .modal-body {
            /* Optional: Set a max-height for the body itself to control the image area */
            max-height: 60vh; /* Example: 60% of viewport height. Adjust as needed. */
            overflow-y: auto; /* Add scroll if content exceeds max-height */
            display: flex; /* Use flexbox to easily center image vertically */
            align-items: center; /* Center image vertically */
            justify-content: center; /* Center image horizontally */
        }

        #modalImage {
            max-width: 100%;
            max-height: 100%; /* Ensure image fits within modal-body's max-height */
            height: auto;
            width: auto; /* Allow width to be determined by max-width/max-height */
            display: block;
            margin: 0 auto;
        }

        .modal {
            z-index: 1070;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <a href="ProvincialAgriHome.html" class="header-brand">
            <img src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Department of Agriculture Logo" />
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
                <a href="farmer-announcement.php" class="nav-link">
                    <i class="fas fa-bullhorn"></i> Announcements
                </a>
            </li>
            <li class="nav-item">
                <a href="farmer-apply_for_assistance.php" class="nav-link">
                    <i class="fas fa-file-invoice"></i> Apply for Assistance
                </a>
            </li>
            <li class="nav-item">
                <a href="farmer-planting_status.php" class="nav-link">
                    <i class="fas fa-leaf"></i> Planting Status
                </a>
            </li>
            <li class="nav-item">
                <a href="farmer-progress_tracking.php" class="nav-link active">
                    <i class="fas fa-chart-line"></i> Progress Tracking
                </a>
            </li>
        </ul>
    </nav>

    <!-- Header (fixed to top right) -->
    <div class="card-header card-header-custom d-flex justify-content-end align-items-center">
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <button class="logout-btn" onclick="location.href='farmers-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Content -->
    <main>
        <div class="container">
            <h2 class="page-title"></i>Progress Tracking</h2>
            <p class="text-muted mb-4">Visual overview of your active crops.</p>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-seedling me-2"></i>Active Crop Progress</h5>

                    <?php if (!empty($user_tracked_crops)): ?>
                        <?php foreach ($user_tracked_crops as $crop):
                            // Example: Calculate a simple progress percentage and stage based on status.
                            // In a real application, you'd have crop-specific growth cycles, planting dates, etc.
                            $progress_percent = 0;
                            $progress_stage = "Unknown Stage";
                            $days_since_update = '';

                            if ($crop['update_date']) {
                                $last_update_timestamp = strtotime($crop['update_date']);
                                $current_timestamp = time();
                                $diff_seconds = $current_timestamp - $last_update_timestamp;
                                $days_since_update = floor($diff_seconds / (60 * 60 * 24));
                            }


                            switch ($crop['status']) {
                                case 'Planted':
                                    $progress_percent = 25; // Example: Just planted
                                    $progress_stage = "Early Growth";
                                    // You'd calculate days since planting if you stored a 'planting_date'
                                    // For now, let's use days since update for display
                                    $days_text = ($days_since_update !== '') ? $days_since_update . " Days since update" : "Status: Planted";
                                    break;
                                case 'Growing': // Assuming you might add more detailed statuses in planting_status.php
                                    $progress_percent = 50;
                                    $progress_stage = "Vegetative Stage";
                                    $days_text = ($days_since_update !== '') ? $days_since_update . " Days since update" : "Status: Growing";
                                    break;
                                case 'Flowering':
                                    $progress_percent = 75;
                                    $progress_stage = "Flowering Stage";
                                    $days_text = ($days_since_update !== '') ? $days_since_update . " Days since update" : "Status: Flowering";
                                    break;
                                case 'Harvesting':
                                    $progress_percent = 90;
                                    $progress_stage = "Ready for Harvest";
                                    $days_text = ($days_since_update !== '') ? $days_since_update . " Days since update" : "Status: Harvesting";
                                    break;
                                case 'Harvested':
                                    $progress_percent = 100;
                                    $progress_stage = "Harvested";
                                    $days_text = ($days_since_update !== '') ? $days_since_update . " Days since harvest" : "Status: Harvested";
                                    break;
                                case 'Not Planted':
                                    $progress_percent = 5;
                                    $progress_stage = "Planning Stage";
                                    $days_text = "Not yet planted";
                                    break;
                                default:
                                    $progress_percent = 0;
                                    $progress_stage = "Status: " . htmlspecialchars($crop['status']);
                                    $days_text = "Last updated: " . date("M d, Y", strtotime($crop['update_date']));
                                    break;
                            }
                        ?>
                            <div class="mb-4 pb-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="progress-label"><?php echo htmlspecialchars($crop['crop_identifier']); ?></span>
                                    <span class="progress-text"><?php echo $days_text; ?></span>
                                </div>
                                <div class="progress" role="progressbar" aria-label="<?php echo htmlspecialchars($crop['crop_identifier']); ?> Progress" aria-valuenow="<?php echo $progress_percent; ?>" aria-valuemin="0" aria-valuemax="100" style="height: 20px;">
                                    <div class="progress-bar progress-bar-custom" style="width: <?php echo $progress_percent; ?>%;">
                                        <?php echo $progress_stage; ?> (<?php echo $progress_percent; ?>%)
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Current Status: <strong><?php echo htmlspecialchars($crop['status']); ?></strong>
                                    <?php if ($crop['photo_path'] && file_exists($crop['photo_path'])): ?>
                                        <!-- MODIFIED: Changed View Photo link to open in modal -->
                                        <a href="#" class="ms-2 view-photo-btn" data-bs-toggle="modal" data-bs-target="#imageViewModal" data-photo-path="<?php echo htmlspecialchars($crop['photo_path']); ?>">
                                            <i class="fas fa-camera"></i> View Photo
                                        </a>
                                    <?php endif; ?>
                                </small>
                                <div class="mt-2">
                                    <!-- You can link 'View Details' to a more specific page or modal later -->
                                    <button class="btn btn-outline-info btn-sm">View Details</button>
                                    <a href="farmer-planting_status.php">
                                        <button class="btn btn-outline-primary btn-sm">Update Status</button>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info text-center" role="alert">
                            <i class="fas fa-info-circle me-2"></i> No active crops being tracked yet. Start by adding one!
                        </div>
                    <?php endif; ?>

                    <a href="farmer-planting_status.php" class="btn btn-theme mt-3"><i class="fas fa-plus me-1"></i> Add New Crop for Tracking</a>
                </div>
            </div>
        </div>
    </main>

    <!-- Image View Modal -->
    <div class="modal fade" id="imageViewModal" tabindex="-1" aria-labelledby="imageViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageViewModalLabel">Crop Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" id="modalImage" class="img-fluid" alt="Crop Photo">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var imageViewModal = document.getElementById('imageViewModal');
            var modalImage = document.getElementById('modalImage');

            imageViewModal.addEventListener('show.bs.modal', function (event) {
                // Button that triggered the modal
                var button = event.relatedTarget;
                // Extract info from data-photo-path attributes
                var photoPath = button.getAttribute('data-photo-path');

                // Update the modal's content.
                modalImage.src = photoPath;
            });
        });
    </script>
</body>

</html>