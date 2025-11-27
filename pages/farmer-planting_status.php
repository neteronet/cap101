<?php
session_start();

// NOTE: Ensure the path to connection.php is correct based on your file structure.
include '../includes/connection.php';

// --- IMPROVEMENT 1: Robust Connection Check ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? "Connection object not set"));
    header("location: farmers-login.php");
    exit();
}

// --- Check if the user is logged in ---
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: farmers-login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$display_name = 'Farmer';

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

// =================================================================
// --- AUTOMATION CONSTANTS: Crop Lifecycle Configuration (Days) ---
// =================================================================
$CROP_LIFECYCLES = [
    'Rice' => [
        'Growing' => 20,    // Days to reach Vegetative stage
        'Flowering' => 60,  // Days to reach Reproductive stage
        'Harvesting' => 90, // Days to reach Maturation/Harvest
        'MaxDays' => 120
    ],
    'Corn' => [
        'Growing' => 15,
        'Flowering' => 50,
        'Harvesting' => 75,
        'MaxDays' => 100
    ],
    'Default' => [
        'Growing' => 15,
        'Flowering' => 45,
        'Harvesting' => 75,
        'MaxDays' => 90
    ]
];

// =================================================================
// --- PAGINATION SETUP ---
// =================================================================

$overview_page = isset($_GET['overview_page']) && is_numeric($_GET['overview_page']) && $_GET['overview_page'] > 0 ? intval($_GET['overview_page']) : 1;
$overview_limit = 5;
$overview_offset = ($overview_page - 1) * $overview_limit;

// Count distinct crop identifiers for the Overview pagination
$stmt_count_overview = $conn->prepare("SELECT COUNT(DISTINCT crop_identifier) FROM planting_status WHERE user_id = ?");
$total_overview_rows = 0;
if ($stmt_count_overview) {
    $stmt_count_overview->bind_param("i", $user_id);
    $stmt_count_overview->execute();
    $stmt_count_overview->bind_result($total_overview_rows);
    $stmt_count_overview->fetch();
    $stmt_count_overview->close();
}
$total_overview_pages = ceil($total_overview_rows / $overview_limit);

// 2. History Pagination
$history_page = isset($_GET['history_page']) && is_numeric($_GET['history_page']) && $_GET['history_page'] > 0 ? intval($_GET['history_page']) : 1;
$history_limit = 10;
$history_offset = ($history_page - 1) * $history_limit;

$stmt_count_history = $conn->prepare("SELECT COUNT(*) FROM planting_status WHERE user_id = ?");
$total_history_rows = 0;
if ($stmt_count_history) {
    $stmt_count_history->bind_param("i", $user_id);
    $stmt_count_history->execute();
    $stmt_count_history->bind_result($total_history_rows);
    $stmt_count_history->fetch();
    $stmt_count_history->close();
}
$total_history_pages = ceil($total_history_rows / $history_limit);

// 3. Photo Gallery Pagination
$photo_page = isset($_GET['photo_page']) && is_numeric($_GET['photo_page']) && $_GET['photo_page'] > 0 ? intval($_GET['photo_page']) : 1;
$photo_limit = 8;
$photo_offset = ($photo_page - 1) * $photo_limit;

$stmt_count_photo = $conn->prepare("SELECT COUNT(*) FROM planting_status WHERE user_id = ? AND photo_path IS NOT NULL AND photo_path != ''");
$total_photo_rows = 0;
if ($stmt_count_photo) {
    $stmt_count_photo->bind_param("i", $user_id);
    $stmt_count_photo->execute();
    $stmt_count_photo->bind_result($total_photo_rows);
    $stmt_count_photo->fetch();
    $stmt_count_photo->close();
}
$total_photo_pages = ceil($total_photo_rows / $photo_limit);


// Initialize messages and arrays
$success_message = '';
$error_message = '';
$alerts = [];
$user_planting_statuses = [];
$update_history = [];
$photo_gallery_items = [];

// --- FUNCTIONS ---

function getCycleStartDate($conn, $user_id, $crop_identifier)
{
    $stmt = $conn->prepare("SELECT update_date FROM planting_status 
                            WHERE user_id = ? AND crop_identifier = ? AND status = 'Seedling' 
                            ORDER BY update_date DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("is", $user_id, $crop_identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['update_date'];
        }
        $stmt->close();
    }
    return null;
}

function calculateAutomatedProgress($crop_identifier, $current_db_status, $start_date)
{
    global $CROP_LIFECYCLES;

    $parts = explode('-', $crop_identifier);
    $clean_name = trim($parts[0]);
    $clean_name = explode('(', $clean_name)[0];
    $clean_name = trim($clean_name);

    $terminal_states = ['Harvested', 'Damaged (Calamity)', 'Not Planted'];
    if (in_array($current_db_status, $terminal_states)) {
        return [
            'status' => $current_db_status,
            'percent' => ($current_db_status == 'Not Planted') ? 0 : 100,
            'is_automated' => false
        ];
    }

    if (!$start_date) {
        return ['status' => $current_db_status, 'percent' => 0, 'is_automated' => false];
    }

    $start = new DateTime($start_date);
    $now = new DateTime();
    $interval = $start->diff($now);
    $days_elapsed = $interval->days;

    $lifecycle = $CROP_LIFECYCLES[$clean_name] ?? $CROP_LIFECYCLES['Default'];

    $calculated_status = 'Seedling';
    $percent = 10;

    if ($days_elapsed >= $lifecycle['Harvesting']) {
        $calculated_status = 'Harvesting';
        $percent = 90;
    } elseif ($days_elapsed >= $lifecycle['Flowering']) {
        $calculated_status = 'Flowering';
        $percent = 75;
    } elseif ($days_elapsed >= $lifecycle['Growing']) {
        $calculated_status = 'Growing';
        $percent = 50;
    }

    $time_percent = min(95, round(($days_elapsed / $lifecycle['MaxDays']) * 100));
    $final_percent = max($percent, $time_percent);

    return [
        'status' => $calculated_status,
        'percent' => $final_percent,
        'days_elapsed' => $days_elapsed,
        'is_automated' => true
    ];
}

function fetchPaginatedCropUpdatesOverview($conn, $user_id, $offset = 0, $limit = 5)
{
    $statuses = [];
    $sql = "SELECT ps.id, ps.crop_identifier, ps.status, ps.photo_path, ps.update_date 
            FROM planting_status ps
            INNER JOIN (
                SELECT crop_identifier, MAX(id) as max_id
                FROM planting_status 
                WHERE user_id = ?
                GROUP BY crop_identifier
            ) latest ON ps.id = latest.max_id
            ORDER BY ps.update_date DESC
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("iii", $user_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $statuses[] = $row;
        }
        $stmt->close();
    }
    return $statuses;
}

function fetchPaginatedUpdateHistory($conn, $user_id, $offset = 0, $limit = 10)
{
    $history = [];
    $stmt = $conn->prepare("SELECT id, crop_identifier, status, photo_path, update_date 
                            FROM planting_status 
                            WHERE user_id = ? 
                            ORDER BY update_date DESC 
                            LIMIT ? OFFSET ?");
    if ($stmt) {
        $stmt->bind_param("iii", $user_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt->close();
    }
    return $history;
}

function fetchPaginatedPhotoGallery($conn, $user_id, $offset = 0, $limit = 8)
{
    $photos = [];
    $stmt = $conn->prepare("SELECT id, crop_identifier, status, photo_path, update_date 
                            FROM planting_status 
                            WHERE user_id = ? 
                            AND photo_path IS NOT NULL AND photo_path != '' 
                            ORDER BY update_date DESC 
                            LIMIT ? OFFSET ?");
    if ($stmt) {
        $stmt->bind_param("iii", $user_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $photos[] = $row;
        }
        $stmt->close();
    }
    return $photos;
}

function generateAlerts($user_planting_statuses)
{
    $generated_alerts = [];
    foreach ($user_planting_statuses as $status_item) {
        if ($status_item['status'] == 'Damaged (Calamity)') {
            $generated_alerts[] = [
                'type' => 'danger',
                'message' => 'Your crop <strong class="text-danger">' . htmlspecialchars($status_item['crop_identifier']) . '</strong> has been marked as <strong>Damaged</strong>. Please submit required documentation.'
            ];
        }
    }
    return $generated_alerts;
}

// --- HANDLE POST SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $base_crop = $_POST['cropSelect'] ?? $_POST['modalCropIdentifier'] ?? '';
    $batch_name = isset($_POST['cropBatch']) ? trim($_POST['cropBatch']) : '';
    $status = $_POST['statusSelect'] ?? $_POST['plantingStatus'] ?? '';
    $photo_path = NULL;
    $file_upload_success = false;

    $final_crop_identifier = $base_crop;

    if (!empty($batch_name) && isset($_POST['cropSelect'])) {
        $final_crop_identifier = $base_crop . " - " . $batch_name;
    }

    if (empty($final_crop_identifier) || $final_crop_identifier == 'Choose...') {
        $error_message = "Please select a crop.";
    } else {
        if (empty($status) || $status == 'Choose...') {
            $status = 'Not Planted';
        }

        // --- ENHANCED PHOTO UPLOAD LOGIC ---
        if (isset($_FILES['photoUpload']) && $_FILES['photoUpload']['error'] !== UPLOAD_ERR_NO_FILE) {
            
            if ($_FILES['photoUpload']['error'] != UPLOAD_ERR_OK) {
                // Handle non-OK errors (e.g., file too large for PHP config)
                $error_message = "File upload failed with error code: " . $_FILES['photoUpload']['error'] . ". Check file size and server limits.";
                error_log("Photo upload error code: " . $_FILES['photoUpload']['error'] . " for file: " . ($_FILES['photoUpload']['name'] ?? 'N/A'));
            } else {
                $target_dir = "uploads/planting_photos/";
                if (!is_dir($target_dir)) {
                    // Attempt to create directory with permissions (0777 for maximum compatibility, adjust as needed)
                    if (!mkdir($target_dir, 0777, true)) {
                        $error_message = "Could not create upload directory. Check server permissions for: " . $target_dir;
                        error_log("Failed to create directory: " . $target_dir);
                    }
                }
                
                if (empty($error_message)) { // Proceed only if directory is fine
                    $file_name = uniqid() . "_" . basename($_FILES["photoUpload"]["name"]);
                    $target_file = $target_dir . $file_name;
                    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                    $check = @getimagesize($_FILES["photoUpload"]["tmp_name"]);
                    
                    if ($check === false) {
                        $error_message = "File upload failed: Uploaded file is not a valid image.";
                    } elseif ($_FILES["photoUpload"]["size"] > 5000000) { // 5MB limit
                        $error_message = "File upload failed: Photo is too large (max 5MB).";
                    } elseif (!in_array($imageFileType, ["jpg", "png", "jpeg"])) {
                        $error_message = "File upload failed: Invalid file type. Only JPG, JPEG, and PNG are allowed.";
                    } else {
                        // Attempt to move the uploaded file
                        if (move_uploaded_file($_FILES["photoUpload"]["tmp_name"], $target_file)) {
                            $photo_path = $target_file; // Set path for DB
                            $file_upload_success = true;
                        } else {
                            // IMPROVEMENT: Log and set error if move fails (permissions issue is common)
                            $error_message = "File upload failed: Could not move uploaded file. Check directory permissions (0777 or equivalent) for: " . $target_dir;
                            error_log($error_message);
                        }
                    }
                }
            }
            // Ensure photo_path is NULL if file upload failed
            if (!$file_upload_success) {
                $photo_path = NULL; 
            }
        }
        // --- END ENHANCED PHOTO UPLOAD LOGIC ---


        // --- DATABASE INSERTION ---
        if ($final_crop_identifier) {
            $stmt = $conn->prepare("INSERT INTO planting_status (user_id, crop_identifier, status, photo_path, update_date) 
                                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");

            if ($stmt) {
                // $photo_path is NULL or the correct path
                $stmt->bind_param("isss", $user_id, $final_crop_identifier, $status, $photo_path);
                if ($stmt->execute()) {
                    // Redirect with success flag and photo indicator
                    $photo_query = $file_upload_success ? "&photo_uploaded=1" : "";
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1" . $photo_query . "&overview_page=" . $overview_page);
                    exit();
                } else {
                    // Log and set database error
                    $db_error = "Error saving status to database: " . $stmt->error;
                    $error_message = empty($error_message) ? $db_error : $error_message . " (Database Save Failed: " . $stmt->error . ")";
                    error_log("DB Save Error: " . $stmt->error . " for user: " . $user_id);
                }
                $stmt->close();
            } else {
                $error_message = "Failed to prepare database statement: " . $conn->error;
                error_log("DB Prepare Error: " . $conn->error);
            }
        }
    }
}

// --- INITIAL FETCH & SUCCESS MESSAGE HANDLING (MODIFIED) ---

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $photo_success_msg = isset($_GET['photo_uploaded']) && $_GET['photo_uploaded'] == 1 ? ' and your photo was uploaded successfully' : '';
    $success_message = "Status updated successfully!" . $photo_success_msg;
}

// --- END OF MODIFIED PHP BLOCK ---

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Status updated successfully!";
}


// --- INITIAL FETCH ---
$user_planting_statuses = fetchPaginatedCropUpdatesOverview($conn, $user_id, $overview_offset, $overview_limit);
$update_history = fetchPaginatedUpdateHistory($conn, $user_id, $history_offset, $history_limit);
$photo_gallery_items = fetchPaginatedPhotoGallery($conn, $user_id, $photo_offset, $photo_limit);
$alerts = generateAlerts($user_planting_statuses); // $alerts is no longer used in the HTML but keeping logic for now

// FIX: Do NOT close connection here, because getCycleStartDate needs it in the HTML loop
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Account - Planting Status & Tracking</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts (UPDATED for consistency) -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom Styles -->
    <style>
        body {
            font-family: "Poppins", sans-serif;
            background: #f8f9fa;
            font-size: 16px;
            line-height: 1.6;
            color: #212529;
            margin: 0;
        }

        /* --- Sidebar Styles (UPDATED for font consistency) --- */
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
            /* CONSISTENT FONT */
            font-family: "Be Vietnam Pro", sans-serif; 
        }
        
        /* >>> INSERTION: Sidebar Menu Label Style <<< */
        .sidebar-menu-label {
            color: rgba(255, 255, 255, 0.7); /* Slightly transparent white */
            padding: 0 1rem 0.5rem 1rem; /* Padding to align with links */
            font-size: 0.75rem; /* Small text */
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
            /* Changed from column to row */
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-start; /* Aligns content to the start */
            text-decoration: none;
            margin-bottom: 2rem;
            padding: 0 1rem; /* Added padding to align with links */
        }

        .sidebar .header-brand img {
            /* Reduced size significantly */
            width: auto;
            max-width: 40px; 
            height: auto;
            background: #19860f;
            padding: 2px; /* Reduced padding */
            border-radius: 4px;
        }

        .sidebar .header-brand div {
            /* Adjusted font size and spacing to place it beside the logo */
            font-size: 18px; 
            font-weight: 700; /* Increased weight for prominence */
            color: #fff;
            margin-top: 0; /* Removed previous vertical margin */
            margin-left: 8px; /* Spacing between logo and text */
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

        /* --- Fixed Top Header (UPDATED for font and alignment consistency) --- */
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
            /* CONSISTENT ALIGNMENT */
            align-items: center;
            justify-content: space-between; 
            /* CONSISTENT FONT */
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

        /* Small text improvements */
        .text-muted {
            color: #6c757d !important;
        }

        .form-text {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }

        /* View photo link styling */
        .view-photo-btn {
            color: #19860f;
            transition: color 0.2s ease;
        }

        .view-photo-btn:hover {
            color: #146c0b;
            text-decoration: underline !important;
        }

        /* 1. Color Palette Standardization: Links */
        a {
            color: #19860f;
        }

        a:hover {
            color: #146c0b;
        }

        /* 5. Typography Consistency: Card Titles (UPDATED for font consistency) */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .card-title,
        .modal-title {
            /* CONSISTENT FONT */
            font-family: "Be Vietnam Pro", sans-serif; 
            color: #0f5132; /* Dark Green */
        }

        /* 1. Color Palette Standardization: Pagination Active State */
        .pagination .page-item.active .page-link {
            background-color: #19860f !important;
            border-color: #19860f !important;
            color: #fff !important;
        }
        .pagination .page-item.active .page-link:focus {
            box-shadow: 0 0 0 0.25rem rgba(25, 134, 15, 0.5) !important; /* Theme Green shadow */
        }

        /* 2. Button and Alert Unification: Button Theme (UPDATED for font consistency) */
        .btn-theme {
            background-color: #19860f;
            color: #fff;
            border-color: #19860f;
            /* CONSISTENT FONT */
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .btn-theme:hover {
            background-color: #146c0b;
            border-color: #146c0b;
            color: #fff;
        }
        
        /* 2. Button and Alert Unification: Outline Button Theme (UPDATED for font consistency) */
        .btn-outline-theme {
            color: #19860f;
            border-color: #19860f;
            /* CONSISTENT FONT */
            font-family: "Be Vietnam Pro", sans-serif;
        }
        .btn-outline-theme:hover,
        .btn-outline-theme:active {
            background-color: #146c0b;
            color: #fff;
            border-color: #146c0b;
        }
        .btn-outline-theme:focus {
            box-shadow: 0 0 0 0.25rem rgba(25, 134, 15, 0.5);
        }

        /* Custom alerts using consistent palette (existing) */
        .alert-custom-success {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
            border-radius: .375rem;
            padding: .75rem 1rem;
        }

        .alert-custom-danger,
        .alert-custom-critical {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
            border-radius: .375rem;
            padding: .75rem 1rem;
        }

        .alert-custom-warning {
            background-color: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
            border-radius: .375rem;
            padding: .75rem 1rem;
            display: flex;
            gap: .75rem;
            align-items: flex-start;
        }

        .alert-custom-info {
            background-color: #cff4fc;
            color: #055160;
            border: 1px solid #b6effb;
            border-radius: .375rem;
            padding: .75rem 1rem;
            display: flex;
            gap: .75rem;
            align-items: flex-start;
        }

        .alert-custom-warning i,
        .alert-custom-info i,
        .alert-custom-danger i {
            margin-top: .2rem;
        }

        .alert-heading {
            color: inherit;
        }

        .progress-label {
            color: #0f5132;
            font-weight: 600;
        }

        .progress-text {
            color: #495057;
        }

        /* Tables and badges color consistency */
        .table thead th {
            color: #0f5132;
        }

        /* Bootstrap default badge overrides for consistency */
        .badge.bg-info { background-color: #0dcaf0 !important; color: #052C65; }
        .badge.bg-success { background-color: #198754 !important; }
        .badge.bg-secondary { background-color: #6c757d !important; }
        .badge.bg-primary { background-color: #0d6efd !important; }
        .badge.bg-warning { background-color: #ffc107 !important; color: #000; }
        .badge.bg-danger { background-color: #dc3545 !important; }

        #sidebarToggleBtn {
            color: #0f5132;
        }

        #sidebarToggleBtn:hover {
            color: #146c0b;
        }
        
        /* 3. Modal Theming Finalization: General Modal Styles */
        .modal {
            z-index: 1070 !important;
        }

        .modal-backdrop {
            z-index: 1065 !important;
        }

        /* Modal Header Unification */
        .modal-header {
            background-color: #19860f; /* Theme Green */
            color: white;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }
        .modal-header .modal-title {
            color: white !important; /* Ensure white text */
        }

        .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%); /* Inverted (White) Close Button */
        }
        
        /* Modal Footer Unification */
        .modal-footer {
            background-color: #f8f9fa; /* Light background */
            border-top: 1px solid #eee; /* Light grey border */
        }

        /* Design: Make the modal look cleaner and more distinct (General) */
        .modal-content {
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-radius: 12px;
        }

        /* ... (rest of the existing modal styles for updateStatusModal) ... */
        
        /* Style the radio label container when selected */
        .option-label {
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #dee2e6;
        }

        .option-label:hover {
            background-color: #f1f8f0;
        }

        /* When the radio inside is checked, change the container style */
        .btn-check:checked+.option-label {
            background-color: #e6f6e4;
            /* Light Green Background */
            border-color: #19860f;
            /* Theme Green Border */
            color: #0f5132;
            /* Dark Green Text */
            font-weight: 600;
            box-shadow: 0 0 0 0.25rem rgba(25, 134, 15, 0.25);
        }

        /* Specific style for Damaged/Calamity when checked */
        .btn-check:checked+.option-label-danger {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #842029;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }

        /* Hide the actual radio circle for a cleaner look */
        .btn-check {
            position: absolute;
            clip: rect(0, 0, 0, 0);
            pointer-events: none;
        }

        /* --- UPDATED MODAL STYLES (Update Status Modal) --- */

        /* Make the modal slightly smaller and tighter */
        #updateStatusModal .modal-dialog {
            max-width: 600px;
            /* Reduced width from default */
        }

        #updateStatusModal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        /* Compact Header */
        #updateStatusModal .modal-header {
            background-color: #19860f;
            padding: 12px 20px;
        }

        #updateStatusModal .modal-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #ffffff;
            /* Ensure white text on green header */
        }

        #updateStatusModal .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
            opacity: 0.8;
        }

        /* Compact Body Styling */
        #updateStatusModal .modal-body {
            padding: 20px;
            background-color: #ffffff;
        }

        /* Redesigned Option Labels (Radio buttons) */
        #updateStatusModal .option-label {
            padding: 10px 15px !important;
            /* Reduced padding */
            border-radius: 10px !important;
            border: 1px solid #eaeaea;
            margin-bottom: 8px;
            /* Tighter spacing */
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
        }

        #updateStatusModal .option-label:hover {
            background-color: #f8fcf8;
            border-color: #19860f;
            transform: translateY(-1px);
        }

        /* Checked State - Green Theme */
        #updateStatusModal .btn-check:checked+.option-label {
            background-color: #f0f9f0;
            border-color: #19860f;
            box-shadow: 0 2px 5px rgba(25, 134, 15, 0.15);
        }

        /* Checked State - Danger Theme */
        #updateStatusModal .btn-check:checked+.option-label-danger {
            background-color: #fff5f5;
            border-color: #dc3545;
            color: #842029;
        }

        /* Icon and Text Sizing */
        #updateStatusModal .option-label i {
            font-size: 1.1rem !important;
            width: 30px;
            color: #19860f;
        }

        #updateStatusModal .option-label-danger i {
            color: #dc3545;
        }

        #updateStatusModal .fw-bold {
            font-size: 0.9rem;
            /* Smaller font for titles */
            color: #2c3e50;
        }

        #updateStatusModal .small {
            font-size: 0.75rem;
            /* Smaller description */
            color: #7f8c8d;
        }

        /* Selected Crop Badge */
        #updateStatusModal .crop-badge {
            background-color: #e8f5e9;
            color: #1b5e20;
            border-radius: 8px;
            padding: 10px;
            border: 1px dashed #a5d6a7;
        }

        /* Footer & Buttons */
        #updateStatusModal .modal-footer {
            padding: 12px 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #eee;
        }

        #updateStatusModal .btn-theme {
            font-size: 0.9rem;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        /* --- INSERTION: Adjust Update Status Modal Height --- */
        #updateStatusModal .modal-content {
            max-height: 90vh;
            /* Limits the total modal height to 90% of the viewport */
            overflow: hidden;
            /* Prevents outer scrolling */
        }

        #updateStatusModal .modal-body {
            max-height: 60vh;
            /* Specific height limit for the scrollable area */
            overflow-y: auto;
            /* Enables vertical scrolling inside the body */
            scrollbar-width: thin;
            /* Makes scrollbar thinner on Firefox */
        }

        /* Webkit scrollbar styling for Chrome/Safari/Edge */
        #updateStatusModal .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        #updateStatusModal .modal-body::-webkit-scrollbar-thumb {
            background-color: rgba(25, 134, 15, 0.3);
            border-radius: 4px;
        }

        /* --- INSERTION: Consistent View Photo Modal Styling --- */
        #imageViewModal .modal-content {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            /* Ensures header corners are respected */
        }

        #imageViewModal .modal-header {
            background-color: #19860f;
            /* Theme Green */
            color: white;
            border-bottom: 1px solid #146c0b;
        }

        #imageViewModal .modal-title {
            color: white !important;
            /* Force white text over green */
            font-weight: 600;
        }

        #imageViewModal .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
            /* White X icon */
            opacity: 0.9;
        }

        #imageViewModal .modal-footer {
            background-color: #f8f9fa;
            /* Light gray footer */
            border-top: 1px solid #eee;
        }
    </style>
</head>

<body>
    <!-- Sidebar (DO NOT TOUCH) -->
    <nav class="sidebar">
        <!-- HTML structure for the logo and text remains the same, but the CSS changes the layout -->
        <a href="ProvincialAgriHome.html" class="header-brand">
            <img src="../photos/logo.png" alt="Department of Agriculture Logo" />
            <div>Agriconnect</div>
        </a>
        
        <!-- START NEW INSERTION: Main Menu Label -->
        <div class="sidebar-menu-label">Main Menu</div>
        <!-- END NEW INSERTION -->

        <ul class="nav flex-column">
            <li class="nav-item"><a href="farmer-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="farmer-apply_for_assistance.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i>Apply for Assistance</a></li>
            <li class="nav-item"><a href="farmer-planting_status.php" class="nav-link active"><i class="fas fa-leaf"></i> Planting Status</a></li>
            <li class="nav-item"><a href="farmer-claim_history.php" class="nav-link"><i class="fas fa-history"></i> Claim History</a></li>
            <li class="nav-item"><a href="farmer-announcement.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li class="nav-item"><a href="farmer-my_profile.php" class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a></li>
        </ul>
        <div class="sidebar-logout">
            <a href="farmers-logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Header (fixed to top right) (HTML structure is correct, CSS handles consistency) -->
    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
        <button id="sidebarToggleBtn" class="btn btn-link p-0 text-dark" title="Toggle Sidebar" style="font-size: 1.5rem;">
            <i class="fas fa-bars"></i>
        </button>
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
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
                
                <!-- Planting Status Card (Form) - MODIFIED to col-md-12 -->
                <div class="col-md-12 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <!-- 5. Typography Consistency: Removed inline text-success class (h5.card-title handled by CSS) -->
                            <h5 class="card-title"><i class="fas fa-plus-circle me-2"></i>Add Planting Status</h5>
                            <p class="text-muted small mb-3">Add a new record here. If you are adding a new batch of the same crop, give it a unique Batch Name.</p>

                            <form method="POST" enctype="multipart/form-data" id="statusUpdateForm">
                                <div class="row">
                                    <div class="col-md-7 mb-3">
                                        <label for="cropSelect" class="form-label fw-bold">Select Crop</label>
                                        <select class="form-select" id="cropSelect" name="cropSelect" required>
                                            <option value="">Choose...</option>
                                            <option value="Rice" <?php echo (isset($crop_identifier) && $crop_identifier == 'Rice') ? 'selected' : ''; ?>>Rice</option>
                                            <option value="Corn" <?php echo (isset($crop_identifier) && $crop_identifier == 'Corn') ? 'selected' : ''; ?>>Corn</option>
                                            <option value="Pechay">Pechay</option>
                                            <option value="Kangkong">Kangkong</option>
                                            <option value="Mustasa">Mustasa</option>
                                            <option value="Alugbati">Alugbati</option>
                                            <option value="Malunggay">Malunggay</option>
                                            <option value="Sitaw">Sitaw</option>
                                            <option value="Ampalaya">Ampalaya</option>
                                            <option value="Okra">Okra</option>
                                            <option value="Talong">Talong</option>
                                            <option value="Kamatis">Kamatis</option>
                                            <option value="Sibuyas">Sibuyas</option>
                                            <option value="Kadyos">Kadyos</option>
                                            <option value="Kamote">Kamote</option>
                                            <option value="Gabi">Gabi</option>
                                            <option value="Carrots">Carrots</option>
                                            <option value="Radish">Radish</option>
                                            <option value="Cassava">Cassava</option>
                                        </select>
                                    </div>
                                    <!-- Batch/Plot Name -->
                                    <div class="col-md-5 mb-3">
                                        <label for="cropBatch" class="form-label fw-bold">Batch/Plot (Optional)</label>
                                        <input type="text" class="form-control" id="cropBatch" name="cropBatch" placeholder="e.g. Field 1">
                                    </div>
                                </div>

                                <!-- REMOVED: Status Selection Dropdown -->
                                <!-- ADDED: Hidden input to default new entries to 'Seedling' -->
                                <input type="hidden" name="statusSelect" value="Seedling">

                                <!-- 3. Alert Unification: Replaced alert-light border with alert-custom-info -->
                                <div class="alert-custom-info d-flex align-items-center mb-3" role="alert">
                                    <!-- Note: Inline style for seedling icon color maintained for precision -->
                                    <i class="fas fa-seedling me-2 fs-5" style="color: #19860f !important;"></i>
                                    <div class="small">New crops will automatically start at the <strong>Seedling</strong> stage.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="photoUpload" class="form-label">Upload Photo (optional)</label>
                                    <input class="form-control" type="file" id="photoUpload" name="photoUpload" accept="image/jpeg,image/png">
                                </div>

                                <!-- 2. Button Theming Enforcement: Ensures .btn-theme is used -->
                                <button type="submit" class="btn btn-theme w-100"><i class="fas fa-plus me-2"></i>Add New Planting</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Latest Crop Updates Overview -->
            <!-- 1. Card and Shadow Unification: Added shadow-sm -->
            <div class="card mb-4 mt-3 shadow-sm" id="latest-updates-overview">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-chart-line me-2"></i>Latest Crop Updates Overview (Page <?php echo $overview_page; ?>)</h5>

                    <?php if (!empty($user_planting_statuses)): ?>
                        <?php foreach ($user_planting_statuses as $crop):
                            $cycle_start_date = getCycleStartDate($conn, $user_id, $crop['crop_identifier']);

                            if (!$cycle_start_date && !in_array($crop['status'], ['Harvested', 'Not Planted', 'Damaged (Calamity)'])) {
                                $cycle_start_date = $crop['update_date'];
                            }

                            $auto_data = calculateAutomatedProgress($crop['crop_identifier'], $crop['status'], $cycle_start_date);

                            // 1. Get the automatically calculated status and progress
                            $auto_status = $auto_data['status'];
                            $auto_percent = $auto_data['percent'];
                            $is_automated = $auto_data['is_automated']; // Preserve the automation flag
                            $days_elapsed = $auto_data['days_elapsed'] ?? 0;
                            $db_status = $crop['status']; // The last status saved in the database

                            // 2. Determine the fixed percentage for the last status saved in the DB
                            $fixed_percent_for_db_status = 0;
                            switch ($db_status) {
                                case 'Seedling':
                                    $fixed_percent_for_db_status = 10;
                                    break;
                                case 'Growing':
                                    $fixed_percent_for_db_status = 50;
                                    break;
                                case 'Flowering':
                                    $fixed_percent_for_db_status = 75;
                                    break;
                                case 'Harvesting':
                                    $fixed_percent_for_db_status = 90;
                                    break;
                                case 'Harvested':
                                case 'Damaged (Calamity)':
                                    $fixed_percent_for_db_status = 100;
                                    $is_automated = false; // Terminal states should stop automation
                                    break;
                                case 'Not Planted':
                                    $fixed_percent_for_db_status = 0;
                                    $is_automated = false;
                                    break;
                                default:
                                    $fixed_percent_for_db_status = 0;
                            }

                            // 3. Determine the final status to display and the progress bar percentage
                            $display_status = $db_status; // Always display the last manual status unless automation is ahead

                            // If the automation's time-based progress is greater than the fixed percentage for the last manual status,
                            // it means the crop has moved past the last status set, so we display the automated status and progress.
                            if ($auto_percent > $fixed_percent_for_db_status && $is_automated) {
                                $progress_percent = $auto_percent;
                                // Only update the display status if the automated stage is further along than the manual one
                                // We use a simplified comparison of the fixed percentages to determine "further along"
                                if ($fixed_percent_for_db_status < 90) { // Don't override Harvesting/Harvested
                                    $display_status = $auto_status;
                                }
                            } else {
                                // Otherwise, display the last manual status, but use the MAX of the fixed percent and the time-based percent
                                // This allows the progress bar to move slightly *past* the fixed percentage (e.g., 75% -> 76%)
                                // until it hits the next automated milestone or the MaxDays limit.
                                $progress_percent = max($fixed_percent_for_db_status, $auto_percent);
                            }
                            // Ensure terminal states are set to 100% and non-automated
                            if (in_array($display_status, ['Harvested', 'Damaged (Calamity)'])) {
                                $progress_percent = 100;
                                $is_automated = false;
                            }

                            // Ensure "Not Planted" is 0% and non-automated
                            if ($display_status === 'Not Planted') {
                                $progress_percent = 0;
                                $is_automated = false;
                            }

                            $status_class = 'secondary';
                            $progress_stage = "Unknown";

                            switch ($display_status) {
                                case 'Seedling':
                                    $progress_stage = "Seedling Stage";
                                    $status_class = 'primary';
                                    break;
                                case 'Growing':
                                    $progress_stage = "Vegetative Stage";
                                    $status_class = 'info';
                                    break;
                                case 'Flowering':
                                    $progress_stage = "Flowering/Fruiting";
                                    $status_class = 'warning';
                                    break;
                                case 'Harvesting':
                                    $progress_stage = "Ready for Harvest";
                                    $status_class = 'success';
                                    break;
                                case 'Harvested':
                                    $progress_stage = "Harvest Complete";
                                    $status_class = 'success';
                                    break;
                                case 'Damaged (Calamity)':
                                    $progress_stage = "Critically Damaged";
                                    $status_class = 'danger';
                                    break;
                                case 'Not Planted':
                                    $progress_stage = "Not Planted";
                                    $status_class = 'secondary';
                                    break;
                                default:
                                    $progress_stage = $display_status;
                            }

                            $days_text = ($is_automated) ? $days_elapsed . " Day(s) since planting" : "Last updated: " . date("M d", strtotime($crop['update_date']));
                        ?>
                            <div class="mb-4 pb-4 border-bottom border-light">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <span class="progress-label"><?php echo htmlspecialchars($crop['crop_identifier']); ?></span>
                                        <?php if ($is_automated && $display_status != $crop['status']): ?>
                                            <span class="badge bg-light text-dark border ms-2" style="font-size: 0.75rem;">
                                                <i class="fas fa-magic text-warning me-1"></i>Auto-Tracked
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="progress-text small"><?php echo $days_text; ?></span>
                                </div>

                                <div class="progress mb-3" role="progressbar" aria-label="<?php echo htmlspecialchars($crop['crop_identifier']); ?> Progress" aria-valuenow="<?php echo $progress_percent; ?>" aria-valuemin="0" aria-valuemax="100" style="height: 25px;">
                                    <!-- Progress Bar Component Details: Logic for classes remains, progress-bar-custom removed in CSS -->
                                    <div class="progress-bar bg-<?php echo $status_class; ?> progress-bar-striped <?php echo ($is_automated && $display_status != 'Harvested') ? 'progress-bar-animated' : ''; ?>" style="width: <?php echo $progress_percent; ?>%;">
                                        <?php echo $progress_stage; ?> (<?php echo $progress_percent; ?>%)
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        Current Status: <span class="badge bg-<?php echo $status_class; ?> me-2"><strong><?php echo htmlspecialchars($display_status); ?></strong></span>
                                        <?php if ($crop['photo_path'] && file_exists($crop['photo_path'])): ?>
                                            <a href="#" class="ms-3 view-photo-btn text-decoration-none" data-bs-toggle="modal" data-bs-target="#imageViewModal" data-photo-path="<?php echo htmlspecialchars($crop['photo_path']); ?>" data-crop-name="<?php echo htmlspecialchars($crop['crop_identifier']); ?>">
                                                <i class="fas fa-camera"></i> View Photo
                                            </a>
                                        <?php endif; ?>
                                    </small>
                                    <div class="d-flex gap-2">
                                        <!-- 2. Button Theming Enforcement: Changed btn-outline-info to btn-outline-theme -->
                                        <button class="btn btn-outline-theme btn-sm"
                                            onclick="openUpdateModal('<?php echo htmlspecialchars($crop['crop_identifier']); ?>', '<?php echo htmlspecialchars($display_status); ?>', <?php echo $crop['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                            <?php echo ($display_status == 'Harvesting') ? 'Confirm Harvest' : 'Update Status'; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- 3. Alert Unification: Replaced alert-info with alert-custom-info -->
                        <div class="alert-custom-info text-center py-4" role="alert">
                            <i class="fas fa-info-circle me-2"></i> No crops recorded yet. Use the "Update Planting Status" form above to start tracking!
                        </div>
                    <?php endif; ?>

                    <!-- Pagination controls for Overview -->
                    <?php if ($total_overview_pages > 1): ?>
                        <nav aria-label="Overview Page navigation" class="d-flex justify-content-center mt-3">
                            <ul class="pagination">
                                <li class="page-item <?php echo ($overview_page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?overview_page=<?php echo max(1, $overview_page - 1); ?>#latest-updates-overview" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php
                                $start_page = max(1, $overview_page - 2);
                                $end_page = min($total_overview_pages, $start_page + 4);
                                if ($end_page - $start_page < 4) {
                                    $start_page = max(1, $end_page - 4);
                                }
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <!-- 1. Color Palette Standardization: Pagination Active State is now controlled by custom CSS -->
                                    <li class="page-item <?php echo ($overview_page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?overview_page=<?php echo $i; ?>#latest-updates-overview"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($overview_page >= $total_overview_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?overview_page=<?php echo min($total_overview_pages, $overview_page + 1); ?>#latest-updates-overview" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Crop Photo Gallery -->
            <!-- 1. Card and Shadow Unification: Added shadow-sm -->
            <div class="card mb-4 shadow-sm" id="photo-gallery-section">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-images me-2"></i>Crop Photo Gallery (Page <?php echo $photo_page; ?>)</h5>
                    <div class="row g-3">
                        <?php if (!empty($photo_gallery_items)): ?>
                            <?php foreach ($photo_gallery_items as $photo_item): ?>
                                <?php if ($photo_item['photo_path'] && file_exists($photo_item['photo_path'])): ?>
                                    <div class="col-md-4 col-lg-3">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="position-relative">
                                                <img src="<?php echo htmlspecialchars($photo_item['photo_path']); ?>"
                                                    class="card-img-top"
                                                    alt="<?php echo htmlspecialchars($photo_item['crop_identifier']); ?>"
                                                    style="height: 200px; object-fit: cover; cursor: pointer;"
                                                    onclick="viewPhotoModal('<?php echo htmlspecialchars($photo_item['photo_path']); ?>', '<?php echo htmlspecialchars($photo_item['crop_identifier']); ?>')">
                                                <!-- Badge color (bg-success) is correct as requested -->
                                                <span class="badge bg-success position-absolute top-0 end-0 m-2">
                                                    <?php echo htmlspecialchars($photo_item['status']); ?>
                                                </span>
                                            </div>
                                            <div class="card-body p-2">
                                                <h6 class="card-title mb-1 small"><?php echo htmlspecialchars($photo_item['crop_identifier']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date("M d, Y", strtotime($photo_item['update_date'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <!-- 3. Alert Unification: Replaced alert-info with alert-custom-info -->
                                <div class="alert-custom-info text-center py-4">
                                    <i class="fas fa-info-circle me-2"></i> No photos uploaded yet or page is empty.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination controls for Photo Gallery -->
                    <?php if ($total_photo_pages > 1): ?>
                        <nav aria-label="Photo Gallery Page navigation" class="d-flex justify-content-center mt-3">
                            <ul class="pagination">
                                <li class="page-item <?php echo ($photo_page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?photo_page=<?php echo max(1, $photo_page - 1); ?>#photo-gallery-section" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php
                                $start_page = max(1, $photo_page - 2);
                                $end_page = min($total_photo_pages, $start_page + 4);
                                if ($end_page - $start_page < 4) {
                                    $start_page = max(1, $end_page - 4);
                                }
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo ($photo_page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?photo_page=<?php echo $i; ?>#photo-gallery-section"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($photo_page >= $total_photo_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?photo_page=<?php echo min($total_photo_pages, $photo_page + 1); ?>#photo-gallery-section" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Update History Section -->
            <!-- 1. Card and Shadow Unification: Added shadow-sm -->
            <div class="card mb-4 shadow-sm" id="update-history-section">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-history me-2"></i>Update History (Page <?php echo $history_page; ?> of <?php echo $total_history_pages; ?>)</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Crop</th>
                                    <th>Status</th>
                                    <th>Photo</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($update_history)): ?>
                                    <?php foreach ($update_history as $history_item):
                                        if ($history_item['status'] == 'Damaged (Calamity)') {
                                            $history_class = 'danger';
                                        } elseif ($history_item['status'] == 'Growing') {
                                            $history_class = 'info';
                                        } elseif ($history_item['status'] == 'Harvesting' || $history_item['status'] == 'Harvested') {
                                            $history_class = 'success';
                                        } elseif ($history_item['status'] == 'Flowering') {
                                            $history_class = 'warning';
                                        } elseif ($history_item['status'] == 'Seedling') {
                                            $history_class = 'primary';
                                        } else {
                                            $history_class = 'secondary';
                                        }
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($history_item['crop_identifier']); ?></strong></td>
                                            <td>
                                                <span class="badge bg-<?php echo $history_class; ?>">
                                                    <?php echo htmlspecialchars($history_item['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($history_item['photo_path'] && file_exists($history_item['photo_path'])): ?>
                                                    <!-- 2. Button Theming Enforcement: Changed btn-outline-primary to btn-outline-theme -->
                                                    <button class="btn btn-sm btn-outline-theme"
                                                        onclick="viewPhotoModal('<?php echo htmlspecialchars($history_item['photo_path']); ?>', '<?php echo htmlspecialchars($history_item['crop_identifier']); ?>')">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">No photo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date("M d, Y H:i", strtotime($history_item['update_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No update history available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination controls for Update History -->
                    <?php if ($total_history_pages > 1): ?>
                        <nav aria-label="History Page navigation" class="d-flex justify-content-center mt-3">
                            <ul class="pagination">
                                <li class="page-item <?php echo ($history_page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?history_page=<?php echo max(1, $history_page - 1); ?>#update-history-section" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php
                                $start_page = max(1, $history_page - 2);
                                $end_page = min($total_history_pages, $start_page + 4);
                                if ($end_page - $start_page < 4) {
                                    $start_page = max(1, $end_page - 4);
                                }
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo ($history_page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?history_page=<?php echo $i; ?>#update-history-section"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($history_page >= $total_history_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?history_page=<?php echo min($total_history_pages, $history_page + 1); ?>#update-history-section" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Update Status Modal (Adjusted Design - Theming done in CSS) -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <!-- 3. Modal Theming Finalization: Header uses custom CSS styling -->
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel">
                        <i class="fas fa-edit me-2"></i>Update Status
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="POST" enctype="multipart/form-data" id="modalUpdateForm">
                    <div class="modal-body">
                        <input type="hidden" id="modalCropIdentifier" name="modalCropIdentifier">

                        <!-- Compact Crop Display -->
                        <div class="crop-badge d-flex align-items-center mb-3">
                            <i class="fas fa-leaf fs-5 me-3"></i>
                            <div>
                                <div class="small text-uppercase fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">Target Crop</div>
                                <div class="fw-bold text-dark" id="modalCropName" style="font-size: 1rem;">--</div>
                            </div>
                        </div>

                        <p class="mb-2 ms-1 fw-bold text-secondary" style="font-size: 0.8rem;">SELECT NEW STAGE</p>

                        <div class="d-grid gap-2 mb-4">
                            <!-- Growing -->
                            <input type="radio" class="btn-check" name="plantingStatus" id="modalGrowing" value="Growing" required>
                            <label class="option-label" for="modalGrowing">
                                <i class="fas fa-leaf"></i>
                                <div>
                                    <div class="fw-bold">Growing</div>
                                    <div class="small">Vegetative Stage</div>
                                </div>
                            </label>

                            <!-- Flowering -->
                            <input type="radio" class="btn-check" name="plantingStatus" id="modalFlowering" value="Flowering">
                            <label class="option-label" for="modalFlowering">
                                <i class="fas fa-spa"></i>
                                <div>
                                    <div class="fw-bold">Flowering</div>
                                    <div class="small">Reproductive Stage</div>
                                </div>
                            </label>

                            <!-- Harvesting -->
                            <input type="radio" class="btn-check" name="plantingStatus" id="modalHarvesting" value="Harvesting">
                            <label class="option-label" for="modalHarvesting">
                                <i class="fas fa-sickle"></i>
                                <div>
                                    <div class="fw-bold">Ready to Harvest</div>
                                    <div class="small">Maturation Stage</div>
                                </div>
                            </label>

                            <!-- Harvested -->
                            <input type="radio" class="btn-check" name="plantingStatus" id="modalHarvested" value="Harvested">
                            <label class="option-label" for="modalHarvested">
                                <i class="fas fa-check-circle"></i>
                                <div>
                                    <div class="fw-bold">Harvested</div>
                                    <div class="small">Cycle Complete</div>
                                </div>
                            </label>

                            <!-- Damaged -->
                            <input type="radio" class="btn-check" name="plantingStatus" id="modalDamaged(Calamity)" value="Damaged (Calamity)">
                            <label class="option-label option-label-danger" for="modalDamaged(Calamity)">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <div class="fw-bold">Damaged</div>
                                    <div class="small">Report Calamity/Loss</div>
                                </div>
                            </label>
                        </div>

                        <!-- Compact Photo Upload -->
                        <div class="mb-1">
                            <label for="modalPhotoUpload" class="form-label fw-bold small ms-1 mb-1">Proof of Status (Optional)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-camera text-muted"></i></span>
                                <input class="form-control border-start-0 ps-0" type="file" id="modalPhotoUpload" name="photoUpload" accept="image/jpeg,image/png" style="font-size: 0.85rem;">
                            </div>
                        </div>
                    </div>

                    <!-- 3. Modal Theming Finalization: Footer uses custom CSS styling, Save button uses .btn-theme -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm px-3 border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-theme px-4 shadow-sm">Save Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Enhanced Image View Modal (Theming done in CSS) -->
    <div class="modal fade" id="imageViewModal" tabindex="-1" aria-labelledby="imageViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <!-- 3. Modal Theming Finalization: Header uses custom CSS styling -->
                <div class="modal-header">
                    <h5 class="modal-title" id="imageViewModalLabel">Crop Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <!-- 4. Modal Theming Finalization: text-center class on modal-body ensures image centering -->
                <div class="modal-body text-center bg-light">
                    <img src="" id="modalImage" class="img-fluid rounded shadow-sm" alt="Crop Photo" style="max-height: 70vh;">
                    <p class="mt-3 mb-0 fw-bold text-success" id="modalImageCropName"></p>
                </div>
                <!-- 3. Modal Theming Finalization: Footer uses custom CSS styling, Download button uses .btn-theme -->
                <div class="modal-footer">
                    <!-- Download Button uses .btn-theme -->
                    <a href="#" id="downloadPhotoLink" class="btn btn-theme" download>
                        <i class="fas fa-download me-2"></i>Download Photo
                    </a>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript for Modals and Sidebar Toggle (UPDATED for local storage persistence) -->
    <script>
        // View Photo Modal (Updated to handle Download Link)
        function viewPhotoModal(photoPath, cropName) {
            // Update Image
            document.getElementById('modalImage').src = photoPath;

            // Update Title and Text
            document.getElementById('imageViewModalLabel').textContent = cropName;
            document.getElementById('modalImageCropName').textContent = cropName;

            // Update Download Link Feature
            const downloadLink = document.getElementById('downloadPhotoLink');
            if (downloadLink) {
                downloadLink.href = photoPath;
                // Create a clean filename for download (e.g., Rice_Image.jpg)
                const cleanName = cropName.replace(/[^a-z0-9]/gi, '_').toLowerCase();
                const extension = photoPath.split('.').pop();
                downloadLink.setAttribute('download', cleanName + '_update.' + extension);
            }

            var modal = new bootstrap.Modal(document.getElementById('imageViewModal'));
            modal.show();
        }

        // Open Update Status Modal
        function openUpdateModal(cropIdentifier, currentStatus, cropId) {
            document.getElementById('modalCropIdentifier').value = cropIdentifier;
            document.getElementById('modalCropName').textContent = cropIdentifier;

            // Uncheck all radio buttons first
            document.querySelectorAll('input[name="plantingStatus"]').forEach(radio => radio.checked = false);

            // Set current status as checked
            let radioId = 'modal' + currentStatus.replace(/[\s()]/g, '');
            if (currentStatus === 'Damaged (Calamity)') {
                radioId = 'modalDamaged(Calamity)';
            }

            const radio = document.getElementById(radioId);
            if (radio) {
                radio.checked = true;
            }

            // --- INSERTION: Reset scroll position to top ---
            document.querySelector('#updateStatusModal .modal-body').scrollTop = 0;

            var modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
            modal.show();
        }

        // --- Sidebar Toggle Logic (Consistent with dashboard.php) ---
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('main');
        const header = document.querySelector('.card-header-custom');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        // sidebarLinks is no longer needed but was removed/commented out previously.

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
        // --- END Sidebar Toggle Logic ---
    </script>
</body>

</html>
<?php
if (isset($conn) && $conn) {
    $conn->close();
}
?>