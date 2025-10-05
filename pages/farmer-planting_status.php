<?php
session_start();

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

// Initialize messages
$success_message = '';
$error_message = '';
$alerts = []; // To store dynamic alerts from the database
$user_planting_statuses = []; // Initialize here

// --- Function to fetch user's current planting statuses ---
function fetchUserPlantingStatuses($conn, $user_id) {
    $statuses = [];
    $stmt = $conn->prepare("SELECT crop_identifier, status, photo_path, update_date FROM planting_status WHERE user_id = ? ORDER BY update_date DESC");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $statuses[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare statement for fetching user planting statuses: " . $conn->error);
    }
    return $statuses;
}

// --- Function to generate alerts based on planting statuses ---
function generateAlerts($user_planting_statuses) {
    $generated_alerts = [];

    // Example: Alert if 'Corn (Field 2)' is 'Not Planted'
    $corn_field2_status_found = false;
    foreach ($user_planting_statuses as $status_item) {
        if ($status_item['crop_identifier'] == 'Corn (Field 2)') {
            $corn_field2_status_found = true;
            if ($status_item['status'] == 'Not Planted') {
                $generated_alerts[] = [
                    'type' => 'warning',
                    'message' => 'Please update the planting status for your <strong class="text-dark">Corn crop (Field 2)</strong>. It is currently marked as "Not Planted".'
                ];
            }
            // You can add more complex logic here, e.g., "not updated in X days"
            // $last_update_timestamp = strtotime($status_item['update_date']);
            // if (time() - $last_update_timestamp > (7 * 24 * 60 * 60)) { // 7 days
            //     $generated_alerts[] = [
            //         'type' => 'warning',
            //         'message' => 'Your <strong class="text-dark">Corn crop (Field 2)</strong> hasn\'t been updated in over a week.'
            //     ];
            // }
            break;
        }
    }
    // General alert if no planting status is recorded at all
    if (empty($user_planting_statuses)) {
        $generated_alerts[] = [
            'type' => 'info', // Changed to info, as it's less critical than a specific "not planted" warning
            'message' => 'You haven\'t recorded any planting status yet. Please use the form to submit your first update!'
        ];
    } elseif (!$corn_field2_status_found) {
        // If Corn (Field 2) is a required crop for the farmer but not found in their list,
        // you might want to add a specific prompt here.
        // For simplicity, we'll just rely on the general "empty" alert above if no crops exist.
        // If 'Corn (Field 2)' is an *expected* crop for all farmers, you might add:
        // $generated_alerts[] = [
        //     'type' => 'info',
        //     'message' => 'Consider adding a planting status for <strong class="text-dark">Corn (Field 2)</strong> if you are cultivating it.'
        // ];
    }


    return $generated_alerts;
}


// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $crop_identifier = $_POST['cropSelect'] ?? '';
    $planting_status_val = $_POST['plantingStatus'] ?? '';
    $photo_path = NULL; // Default to NULL

    // Basic validation
    if (empty($crop_identifier) || $crop_identifier == 'Choose...' || empty($planting_status_val)) {
        $error_message = "Please select a crop and its planting status.";
    } else {
        // Handle file upload
        if (isset($_FILES['photoUpload']) && $_FILES['photoUpload']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "uploads/planting_photos/"; // Create this directory in your project root
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true); // Create directory if it doesn't exist
            }

            $file_name = uniqid() . "_" . basename($_FILES["photoUpload"]["name"]);
            $target_file = $target_dir . $file_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            // Check if image file is a actual image or fake image
            $check = getimagesize($_FILES["photoUpload"]["tmp_name"]);
            if ($check !== false) {
                // Check file size (max 5MB)
                if ($_FILES["photoUpload"]["size"] > 5000000) {
                    $error_message = "Sorry, your file is too large (max 5MB).";
                } else {
                    // Allow certain file formats
                    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
                        $error_message = "Sorry, only JPG, JPEG, & PNG files are allowed.";
                    } else {
                        if (move_uploaded_file($_FILES["photoUpload"]["tmp_name"], $target_file)) {
                            $photo_path = $target_file;
                            // Success message for file upload can be combined with status update message
                        } else {
                            $error_message = "Sorry, there was an error uploading your file.";
                        }
                    }
                }
            } else {
                $error_message = "File is not an image.";
            }
        }

        // Prepare to insert or update the database
        $stmt = $conn->prepare("INSERT INTO planting_status (user_id, crop_identifier, status, photo_path)
                                VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE status = VALUES(status), photo_path = VALUES(photo_path), update_date = CURRENT_TIMESTAMP");

        if ($stmt) {
            $stmt->bind_param("isss", $user_id, $crop_identifier, $planting_status_val, $photo_path);
            if ($stmt->execute()) {
                if (empty($error_message)) { // Only show success if no prior error
                    $success_message = "Planting status updated successfully for " . htmlspecialchars($crop_identifier) . ".";
                    if ($photo_path) {
                        $success_message .= " Your photo has also been uploaded.";
                    }
                    // IMPORTANT: Re-fetch statuses and re-generate alerts immediately after a successful update
                    $user_planting_statuses = fetchUserPlantingStatuses($conn, $user_id);
                    $alerts = generateAlerts($user_planting_statuses);
                }
            } else {
                $error_message = "Error updating planting status: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Database error: Could not prepare statement to update planting status.";
        }
    }
}

// --- Initial Fetch for page load (if not already fetched by form submission) ---
if (empty($user_planting_statuses)) { // Only fetch if not already updated by POST
    $user_planting_statuses = fetchUserPlantingStatuses($conn, $user_id);
}

// --- Generate Alerts for initial page load or if form submission didn't update it ---
if (empty($alerts) && $_SERVER["REQUEST_METHOD"] != "POST") { // Only generate if not already updated by POST
    $alerts = generateAlerts($user_planting_statuses);
}


$conn->close(); // Close the connection after all database operations
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Account - Planting Status</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom Styles -->
    <style>
        /* ... (Your existing CSS styles go here) ... */
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
            background: #19860f; /* Main green */
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
            background-color: #fff; /* Active link background */
            color: #19860f; /* Active link text color */
            font-weight: 600;
        }

        .sidebar .nav-link:hover:not(.active) {
            background-color: #146c0b; /* Darker green on hover */
            color: #fff;
        }

        /* Submenu styles (these styles are no longer directly used for Crop Monitoring,
           but kept in case other dropdowns exist or are added later) */
        .sidebar .nav-item .collapse .nav-link {
            padding-left: 2.5rem; /* Indent for submenu items */
            background-color: #19860f; /* Inherit parent background */
            color: #fff;
            font-size: 0.95rem;
            padding-right: 1rem;
        }
        .sidebar .nav-item .collapse .nav-link.active {
            background-color: #fff; /* Active submenu item background */
            color: #19860f;
            font-weight: 600;
            border-radius: 0;
        }
        .sidebar .nav-item .collapse .nav-link:hover:not(.active) {
            background-color: #146c0b; /* Hover for submenu item */
            color: #fff;
        }

        /* Specific style for the dropdown toggle link itself (no longer needed for crop monitoring) */
        .sidebar .nav-link.dropdown-toggle-custom {
            background-color: transparent;
            color: #fff;
        }
        .sidebar .nav-link.dropdown-toggle-custom:hover {
            background-color: #146c0b;
            color: #fff;
        }
        .sidebar .nav-link.dropdown-toggle-custom[aria-expanded="true"] .fa-chevron-down {
            transform: rotate(180deg);
        }
        .sidebar .nav-link .fa-chevron-down {
            transition: transform 0.2s ease-in-out;
        }


        .sidebar .header-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            margin-bottom: 1rem;
            padding: 0 1rem; /* Padding for the brand area */
        }

        .sidebar .header-brand img {
            width: 100%;
            max-width: 120px;
            height: auto;
            background: #19860f; /* Match sidebar background */
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
            left: 250px; /* Aligned with main content start */
            right: 0;
            height: 56px; /* Standard Bootstrap navbar height */
            background-color: #fff;
            color: #19860f; /* Green text for branding/user info */
            padding: 0 1.25rem;
            font-weight: 500;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: flex-end; /* Align items to the right */
            z-index: 1060; /* Higher than sidebar */
            border-bottom: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); /* Subtle shadow */
        }

        .header-brand span { /* This style is for the "AntiqueProv Agri" in the original header. Not used in this layout. */
            font-size: 1rem;
            font-weight: 600;
            color: #19860f;
        }

        .logout-btn {
            background: #ff4b2b; /* Red */
            color: #fff;
            border: none;
            padding: 6px 14px;
            font-size: 14px;
            border-radius: 20px;
            transition: background 0.2s ease;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: #e04325; /* Darker red on hover */
        }

        /* --- Main Content Area --- */
        main {
            margin-left: 250px; /* Space for the sidebar */
            padding: 1rem 2rem 2rem 2rem;
            padding-top: 72px; /* Space for the fixed top header */
            background: #f8f9fa;
            min-height: 100vh;
        }

        .page-title {
            font-size: 1.8rem; /* Adjusted for consistency */
            font-weight: 600;
            color: #19860f; /* Green */
            margin-bottom: 1rem;
        }

        .card {
            border-radius: 0.5rem; /* Consistent border-radius */
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05); /* Consistent shadow */
            margin-bottom: 1rem;
        }

        .card-title {
            color: #19860f; /* Green title for cards */
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 1.25rem; /* Consistent with dashboard */
        }

        /* --- Custom Elements for Planting Status --- */
        .alert-custom-warning {
            background-color: #fff3cd; /* Light yellow */
            border-color: #ffeeba;
            color: #856404;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        .alert-custom-warning i {
            margin-right: 10px;
            font-size: 1.5rem;
            color: #ffc107; /* Warning yellow icon */
        }
        .alert-custom-warning .alert-heading {
            color: #856404;
            font-weight: 600;
        }

        .list-unstyled li {
            font-size: 0.95rem;
            color: #555;
            margin-bottom: 0.5rem;
        }
        .list-unstyled li i {
            width: 20px; /* Align icons */
            text-align: center;
        }

        .form-label {
            font-weight: 500;
            color: #333;
        }

        .form-check-label {
            font-size: 1rem;
            color: #444;
        }

        .form-check-input:checked {
            background-color: #19860f;
            border-color: #19860f;
        }

        .btn-theme { /* Re-using the btn-theme from dashboard for consistency */
            background-color: #19860f;
            color: #fff;
            font-size: 15px;
            padding: 10px 20px;
            border-radius: 4px;
            transition: background 0.2s ease;
            border: none; /* Ensure no default border */
        }

        .btn-theme:hover {
            background-color: #146c0b;
            color: #fff; /* Keep text white on hover */
        }
        .planting-status-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 8px;
            border: 1px solid #eee;
            border-radius: 5px;
            background-color: #fcfcfc;
        }
        .planting-status-item img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 15px;
            border: 1px solid #ddd;
        }
        .planting-status-item .details {
            flex-grow: 1;
        }
        .planting-status-item .details strong {
            color: #19860f;
            font-size: 1.05rem;
        }
        .planting-status-item .details span {
            display: block;
            font-size: 0.85rem;
            color: #666;
        }
        .alert-custom-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .alert-custom-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
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
                <a href="farmer-planting_status.php" class="nav-link active">
                    <i class="fas fa-leaf"></i> Planting Status
                </a>
            </li>
            <li class="nav-item">
                <a href="farmer-progress_tracking.php" class="nav-link">
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
            <h2 class="page-title"><i class="fas fa-leaf me-2"></i>Planting Status</h2>
            <p class="text-muted mb-4">Update your crop's planting progress and check for alerts.</p>

            <?php if ($success_message): ?>
                <div class="alert alert-custom-success" role="alert">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-custom-danger" role="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Reminders/Alerts Card -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-bell me-2"></i>Reminders & Alerts</h5>
                            <?php if (!empty($alerts)): ?>
                                <?php foreach ($alerts as $alert): ?>
                                    <div class="alert-custom-<?php echo htmlspecialchars($alert['type']); ?> mb-3" role="alert">
                                        <?php if ($alert['type'] == 'warning'): ?>
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <div>
                                                <h6 class="alert-heading mb-1">Action Required!</h6>
                                                <?php echo $alert['message']; ?>
                                            </div>
                                        <?php elseif ($alert['type'] == 'info'): ?>
                                            <i class="fas fa-info-circle me-2"></i>
                                            <div>
                                                <h6 class="alert-heading mb-1">Information:</h6>
                                                <?php echo $alert['message']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info" role="alert">
                                    <i class="fas fa-info-circle me-2"></i>No immediate alerts or reminders. All good!
                                </div>
                            <?php endif; ?>

                            <p class="text-muted small mt-3">Your recorded planting statuses:</p>
                            <?php if (!empty($user_planting_statuses)): ?>
                                <div class="list-group">
                                    <?php foreach ($user_planting_statuses as $status_item): ?>
                                        <div class="planting-status-item">
                                            <?php if ($status_item['photo_path'] && file_exists($status_item['photo_path'])): ?>
                                                <img src="<?php echo htmlspecialchars($status_item['photo_path']); ?>" alt="Crop Photo" class="img-thumbnail">
                                            <?php else: ?>
                                                <img src="https://via.placeholder.com/50?text=No+Photo" alt="No Photo" class="img-thumbnail">
                                            <?php endif; ?>
                                            <div class="details">
                                                <strong><?php echo htmlspecialchars($status_item['crop_identifier']); ?></strong>
                                                <span>Status: <?php echo htmlspecialchars($status_item['status']); ?></span>
                                                <span>Last Updated: <?php echo date("M d, Y H:i", strtotime($status_item['update_date'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No planting statuses recorded yet. Use the form to submit one!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Planting Status Card -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-clipboard-check me-2"></i>Update Planting Status</h5>
                            <form method="POST" enctype="multipart/form-data"> <!-- Added method="POST" and enctype -->
                                <div class="mb-3">
                                    <label for="cropSelect" class="form-label">Select Crop:</label>
                                    <select class="form-select" id="cropSelect" name="cropSelect" aria-label="Select Crop" required>
                                        <option value="Choose..." selected>Choose...</option>
                                        <!-- Populate options dynamically from a database table of registered crops if available -->
                                        <option value="Rice (Field 1)">Rice (Field 1)</option>
                                        <option value="Corn (Field 2)">Corn (Field 2)</option>
                                        <option value="Vegetables (Plot 3)">Vegetables (Plot 3)</option>
                                        <!-- Add more options as needed -->
                                    </select>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="plantingStatus" id="planted" value="Planted" required>
                                    <label class="form-check-label" for="planted">
                                        ✅ Seeds have been planted
                                    </label>
                                </div>

                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="plantingStatus" id="notPlanted" value="Not Planted" required>
                                    <label class="form-check-label" for="notPlanted">
                                        ❌ Seeds not yet planted
                                    </label>
                                </div>

                                <div class="mb-3">
                                    <label for="photoUpload" class="form-label">Upload Crop Photo (optional)</label>
                                    <input class="form-control" type="file" id="photoUpload" name="photoUpload" accept="image/jpeg,image/png">
                                    <div class="form-text">Max file size 5MB. Accepted formats: JPG, PNG.</div>
                                </div>

                                <button type="submit" class="btn btn-theme w-100"><i class="fas fa-upload me-2"></i>Submit Update</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>