<?php
session_start();

// NOTE: Ensure the path to connection.php is correct based on your file structure.
include '../includes/connection.php'; 

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

// Initialize messages
$success_message = '';
$error_message = '';
$alerts = []; // To store dynamic alerts from the database
$user_planting_statuses = []; // Initialize here

// --- Function to fetch user's current planting statuses (USED BY BOTH FILES) ---
function fetchUserPlantingStatuses($conn, $user_id) {
    $statuses = [];
    // Note: The 'id' column is needed for potential future detail pages/tracking
    $stmt = $conn->prepare("SELECT id, crop_identifier, status, photo_path, update_date FROM planting_status WHERE user_id = ? ORDER BY update_date DESC");
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

// --- Function to generate alerts based on planting statuses (FROM FILE 1) ---
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
            break;
        }
    }
    // General alert if no planting status is recorded at all
    if (empty($user_planting_statuses)) {
        $generated_alerts[] = [
            'type' => 'info',
            'message' => 'You haven\'t recorded any planting status yet. Please use the form to submit your first update!'
        ];
    }

    return $generated_alerts;
}


// --- Handle Form Submission (FROM FILE 1) ---
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
        // Assuming a UNIQUE constraint on (user_id, crop_identifier) exists for ON DUPLICATE KEY UPDATE
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
if (empty($alerts)) { // Generate alerts only if POST didn't already
    $alerts = generateAlerts($user_planting_statuses);
}


$conn->close(); // Close the connection after all database operations
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Account - Planting Status & Tracking</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom Styles (Combined from both files) -->
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
            background: #19860f; /* Main green */
            padding: 1rem 0;
            overflow-y: auto;
            font-size: 14px;
            z-index: 1050;
            border-right: 1px solid #ddd; /* Original subtle border kept */
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
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); /* Original subtle shadow kept */
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
            padding: 2rem 3rem 3rem 3rem; /* Increased padding for more airiness */
            padding-top: 80px; /* Space for the fixed top header */
            background: #f8f9fa;
            min-height: 100vh;
        }

        .page-title {
            font-size: 1.9rem;
            font-weight: 600;
            color: #19860f; /* Green */
            margin-bottom: 2rem; /* Increased margin */
            text-align: center;
        }

        .card {
            border-radius: 0.85rem; /* Slightly more rounded */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Original shadow kept */
            margin-bottom: 2rem; /* Consistent spacing */
            border: none; /* Removed original faint border-bottom/border */
            transition: transform 0.3s ease, box-shadow 0.3s ease; /* Transition for smoothness */
        }

        .card:hover {
            /* No lift or stronger shadow, as requested */
        }

        .card-body {
            padding: 2.5rem; /* Increased padding */
        }

        .card-title {
            color: #19860f; /* Green title for cards */
            font-weight: 600;
            margin-bottom: 1.5rem; /* More spacing below title */
            font-size: 1.3rem;
            border-left: 4px solid #19860f; /* Visual accent line */
            padding-left: 10px; /* Space for accent line */
        }

        /* --- Custom Elements for Planting Status & Alerts (FROM FILE 1) --- */
        .alert-custom-warning {
            background-color: #fff3cd; /* Light yellow */
            border-color: #ffeeba;
            color: #856404;
        }
        .alert-custom-info {
            background-color: #cfe2ff; /* Light blue */
            border-color: #b9d1f3;
            color: #084298;
        }
        /* Combined alert styles for consistent look */
        .alert-custom-warning, .alert-custom-info {
            padding: 1.25rem; /* Increased padding */
            border-radius: 0.75rem; /* More rounded */
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start; /* Align icon and text to the top */
        }
        .alert-custom-warning i,
        .alert-custom-info i {
            margin-right: 15px; /* More space between icon and text */
            font-size: 1.8rem; /* Larger icons */
            flex-shrink: 0; /* Prevent icon from shrinking */
        }
        .alert-custom-warning .alert-heading,
        .alert-custom-info .alert-heading {
            font-size: 1.1rem; /* Slightly larger heading */
            font-weight: 600;
            margin-top: -3px; /* Adjust alignment with larger icon */
            /* Color inherited from parent alert */
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
        
        /* Form styling */
        .form-label {
            font-weight: 500;
            color: #333;
        }
        .form-select, .form-control {
            border-radius: 0.5rem; /* Consistent rounding on inputs */
            padding: 0.65rem 1rem; /* Better input padding */
        }

        .form-check-label {
            font-size: 1rem;
            color: #444;
            padding-top: 2px; /* Align text slightly better with radio button */
        }

        .form-check-input:checked {
            background-color: #19860f;
            border-color: #19860f;
        }
        
        /* --- Progress Tracking Specific Styles (FROM FILE 2) --- */
        .progress-bar-custom {
            background-color: #28a745; /* Success green */
            transition: width 0.3s ease;
            font-weight: 600; /* Bold text inside bar */
            font-size: 0.95rem;
            color: #fff; /* Ensure white text for contrast */
            border-radius: 0.4rem; /* Match progress container */
        }

        .progress {
            height: 28px !important; /* Taller progress bar for visibility */
            border-radius: 0.5rem; /* Consistent rounding */
            background-color: #e9ecef; /* Lighter background for empty space */
        }

        .progress-label {
            font-weight: 700; /* Bolder label */
            color: #333;
            margin-bottom: 0.75rem;
            font-size: 1.2rem; /* Slightly larger label */
            text-align: left;
        }

        .progress-text {
            font-size: 0.95em;
            color: #6c757d;
            text-align: right;
        }

        .btn-theme { /* Re-using the btn-theme from dashboard for consistency */
            background-color: #19860f;
            color: #fff;
            font-size: 15px;
            padding: 12px 20px; /* Taller button */
            border-radius: 0.5rem; /* Consistent rounding */
            transition: background 0.2s ease;
            border: none;
            font-weight: 500;
            /* Shadow kept off */
        }

        .btn-theme:hover {
            background-color: #146c0b;
            color: #fff;
        }

        .btn-outline-info {
            color: #17a2b8;
            border-color: #17a2b8;
            transition: all 0.2s ease;
            border-radius: 0.5rem; /* Consistent rounding for detail buttons */
        }

        .btn-outline-info:hover {
            background-color: #17a2b8;
            color: #fff;
        }

        .btn-outline-primary {
            color: #007bff;
            border-color: #007bff;
            transition: all 0.2s ease;
            border-radius: 0.5rem; /* Consistent rounding for detail buttons */
        }

        .btn-outline-primary:hover {
            background-color: #007bff;
            color: #fff;
        }

        /* --- Image View Modal Adjustments (FROM FILE 2) --- */
        .modal {
            z-index: 1070;
        }

        #modalImage {
            max-width: 100%;
            max-height: 100%;
            height: auto;
            width: auto;
            display: block;
            margin: 0 auto;
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
            <li class="nav-item"><a href="farmer-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="farmer-apply_for_assistance.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i>Apply for Assistance</a></li>
            <li class="nav-item"><a href="farmer-planting_status.php" class="nav-link active"><i class="fas fa-leaf"></i> Planting Status</a></li>
            <li class="nav-item"><a href="farmer-claim_history.php" class="nav-link"><i class="fas fa-history"></i> Claim History</a></li>
            <li class="nav-item"><a href="farmer-progress_tracking.php" class="nav-link"><i class="fas fa-chart-line"></i> Progress Tracking</a></li>
            <li class="nav-item"><a href="farmer-announcement.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li class="nav-item"><a href="farmer-my_profile.php" class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a></li>
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
                <!-- Reminders/Alerts Card (FROM FILE 1) -->
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
                                            <i class="fas fa-info-circle"></i>
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
                            <p class="text-muted small mt-3">Use the form opposite to update a status.</p>
                        </div>
                    </div>
                </div>

                <!-- Planting Status Card (Form) (FROM FILE 1) -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-clipboard-check me-2"></i>Update Planting Status</h5>
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="cropSelect" class="form-label">Select Crop:</label>
                                    <select class="form-select" id="cropSelect" name="cropSelect" aria-label="Select Crop" required>
                                        <option value="Choose..." selected>Choose...</option>
                                        <option value="Rice (Field 1)">Rice (Field 1)</option>
                                        <option value="Corn (Field 2)">Corn (Field 2)</option>
                                        <option value="Vegetables (Plot 3)">Vegetables (Plot 3)</option>
                                    </select>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="plantingStatus" id="planted" value="Planted" required>
                                    <label class="form-check-label" for="planted">
                                        ✅ Seeds have been planted (Initial Stage)
                                    </label>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="plantingStatus" id="growing" value="Growing" required>
                                    <label class="form-check-label" for="growing">
                                        🌱 Crop is actively growing (Mid-stage)
                                    </label>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="plantingStatus" id="harvesting" value="Harvesting" required>
                                    <label class="form-check-label" for="harvesting">
                                        🌾 Ready for harvest (Final Stage)
                                    </label>
                                </div>

                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="plantingStatus" id="notPlanted" value="Not Planted" required>
                                    <label class="form-check-label" for="notPlanted">
                                        ❌ Seeds not yet planted (Planning Stage)
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
            
            <!-- Active Crop Progress Card (FROM FILE 2) -->
            <div class="card mb-4 mt-3">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-chart-line me-2"></i>Active Crop Progress Overview</h5>

                    <?php if (!empty($user_planting_statuses)): ?>
                        <?php foreach ($user_planting_statuses as $crop):
                            // Logic to calculate progress percent and stage (Simplified from FILE 2)
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
                                    $progress_percent = 25;
                                    $progress_stage = "Early Growth";
                                    $days_text = ($days_since_update !== '') ? $days_since_update . " Days since update" : "Status: Planted";
                                    break;
                                case 'Growing':
                                    $progress_percent = 50;
                                    $progress_stage = "Vegetative Stage";
                                    $days_text = ($days_since_update !== '') ? $days_since_update . " Days since update" : "Status: Growing";
                                    break;
                                case 'Harvesting':
                                    $progress_percent = 90;
                                    $progress_stage = "Ready for Harvest";
                                    $days_text = ($days_since_update !== '') ? $days_since_update . " Days since update" : "Status: Harvesting";
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
                            <div class="mb-4 pb-4 border-bottom border-light">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="progress-label"><?php echo htmlspecialchars($crop['crop_identifier']); ?></span>
                                    <span class="progress-text"><?php echo $days_text; ?></span>
                                </div>
                                <div class="progress mb-3" role="progressbar" aria-label="<?php echo htmlspecialchars($crop['crop_identifier']); ?> Progress" aria-valuenow="<?php echo $progress_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar progress-bar-custom" style="width: <?php echo $progress_percent; ?>%;">
                                        <?php echo $progress_stage; ?> (<?php echo $progress_percent; ?>%)
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        Current Status: <strong><?php echo htmlspecialchars($crop['status']); ?></strong>
                                        <?php if ($crop['photo_path'] && file_exists($crop['photo_path'])): ?>
                                            <!-- MODIFIED: Changed View Photo link to open in modal (FROM FILE 2) -->
                                            <a href="#" class="ms-3 view-photo-btn text-decoration-none" data-bs-toggle="modal" data-bs-target="#imageViewModal" data-photo-path="<?php echo htmlspecialchars($crop['photo_path']); ?>">
                                                <i class="fas fa-camera"></i> View Photo
                                            </a>
                                        <?php endif; ?>
                                    </small>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-info btn-sm">View Details</button>
                                        <!-- No need for 'Update Status' button linking to the same page, but we keep the structure if needed later -->
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info text-center py-4" role="alert">
                            <i class="fas fa-info-circle me-2"></i> No crops recorded yet. Use the "Update Planting Status" form above to start tracking!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Image View Modal (FROM FILE 2) -->
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

    <!-- JavaScript for Modal (FROM FILE 2) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var imageViewModal = document.getElementById('imageViewModal');
            var modalImage = document.getElementById('modalImage');

            // Listen for when the modal is about to be shown
            imageViewModal.addEventListener('show.bs.modal', function (event) {
                // Button that triggered the modal is in event.relatedTarget
                var button = event.relatedTarget;
                // Extract info from data-photo-path attribute
                var photoPath = button.getAttribute('data-photo-path');

                // Update the modal's content.
                modalImage.src = photoPath;
            });
        });
    </script>
</body>
</html>