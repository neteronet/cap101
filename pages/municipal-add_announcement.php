<?php
session_start();

// NOTE: Ensure the path to connection.php is correct based on your file structure.
// If municipal-add_announcement.php is in 'municipal/' and connection.php is in 'includes/', 
// then '../includes/connection.php' is correct.
include '../includes/connection.php'; 

// Redirect if user_id is not set or not an integer
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: municipal-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Municipal User'; // Default fallback

// Fetch user name (optional, but good for display consistency)
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

// Define available categories
$categories = [
    'advisory',
    'program',
    'alert',
    'general',
    'agriculture'
];

// Define Max File Size (2MB in bytes)
$max_file_size = 2 * 1024 * 1024;

// --- Handle Announcement Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    // RENAMED: $image_url changed to $photo_path
    $photo_path = null; // Will store the path to the uploaded photo

    // 1. Validation
    if (empty($title) || empty($category) || empty($content)) {
        $_SESSION['message'] = "Please fill in all required fields (Title, Category, Content).";
        $_SESSION['message_type'] = "danger";
    } elseif (!in_array($category, $categories)) {
        $_SESSION['message'] = "Invalid category selected.";
        $_SESSION['message_type'] = "danger";
    } else {
        // Flag to check if we can proceed with DB insertion
        $can_insert = true;
        
        // 2. Image Upload Handling
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file_info = $_FILES['image'];
            
            // Handle PHP upload errors
            if ($file_info['error'] !== UPLOAD_ERR_OK) {
                $error_msg = "Image upload failed. ";
                switch ($file_info['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_msg .= "File is too large (check php.ini limits).";
                        break;
                    default:
                        $error_msg .= "Unknown upload error (Code: " . $file_info['error'] . ").";
                        break;
                }
                $_SESSION['message'] = $error_msg;
                $_SESSION['message_type'] = "warning";
                // CRITICAL: We still continue to DB insertion, but without photo_path set.
            } else {
                $file_tmp = $file_info['tmp_name'];
                $file_name = $file_info['name'];
                $file_size = $file_info['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                
                // Define the upload directory relative to this script
                $upload_dir = '../uploads/announcements/'; 
                
                // Max size check
                if ($file_size > $max_file_size) {
                    $_SESSION['message'] = "Image file is too large. Max file size is 2MB.";
                    $_SESSION['message_type'] = "warning";
                } elseif (!in_array($file_ext, $allowed_ext)) {
                    $_SESSION['message'] = "Invalid image file type. Only JPG, JPEG, PNG, GIF are allowed.";
                    $_SESSION['message_type'] = "warning"; 
                } else {
                    // Create the directory if it doesn't exist
                    if (!is_dir($upload_dir)) {
                        // Use error suppression and check result
                        if (!@mkdir($upload_dir, 0777, true)) {
                            error_log("Failed to create upload directory: " . $upload_dir . ". Check directory permissions.");
                            $_SESSION['message'] = "Server configuration error: Cannot create upload directory. Ask administrator to check permissions.";
                            $_SESSION['message_type'] = "danger";
                            $can_insert = false; // Stop insertion if directory cannot be created
                        }
                    }

                    if ($can_insert) {
                        // Generate a unique file name
                        $new_file_name = uniqid('announcement_', true) . '.' . $file_ext;
                        $target_file = $upload_dir . $new_file_name;

                        if (move_uploaded_file($file_tmp, $target_file)) {
                            // Store the relative path for database storage
                            // RENAMED: Storing path in $photo_path
                            $photo_path = 'uploads/announcements/' . $new_file_name; 
                        } else {
                            // The most likely error: Permissions or path issue
                            error_log("Failed to move uploaded file from {$file_tmp} to {$target_file}. Check file and directory permissions on {$upload_dir}.");
                            $_SESSION['message'] = "Error uploading image file (Permissions/Path issue). Announcement published without image.";
                            $_SESSION['message_type'] = "warning"; 
                            // $photo_path remains null, continue to DB insertion
                        }
                    }
                }
            }
        } // End of Image Handling

        // 3. Database Insertion (Only proceed if basic validation passed and no critical error)
        if ($can_insert) {
            // NOTE: The DB schema uses `image`. This SQL query uses the correct column name `image`.
            // The bind parameter will use the value from $photo_path.
            $insert_sql = "INSERT INTO announcements (title, category, content, image, publish_date) VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($insert_sql);

            if ($stmt) {
                // IMPORTANT FIX: Use an empty string '' if no photo was uploaded.
                // RENAMED: Binding from $photo_path
                $bind_image = $photo_path ?? ''; 
                
                // Bind parameters: title (s), category (s), content (s), image (s)
                $stmt->bind_param("ssss", $title, $category, $content, $bind_image);

                if ($stmt->execute()) {
                    // Prepend message if an image warning was set
                    $success_message = "Announcement <strong>" . htmlspecialchars($title) . "</strong> published successfully!";
                    if (isset($_SESSION['message_type']) && $_SESSION['message_type'] === 'warning') {
                        // Keep existing warning message and prepend success text
                        $_SESSION['message'] = "WARNING: Image could not be uploaded, but the announcement was published. " . $_SESSION['message'];
                    } else {
                         $_SESSION['message'] = $success_message;
                         $_SESSION['message_type'] = "success";
                    }
                    
                    $stmt->close();
                    $conn->close();
                    header("Location: municipal-announcements.php");
                    exit();
                } else {
                    // Log the database error
                    error_log("DB Insert Error: " . $stmt->error);
                    $_SESSION['message'] = "Database error: Failed to add announcement. " . $stmt->error;
                    $_SESSION['message_type'] = "danger";
                }
                $stmt->close();
            } else {
                // Log the prepare statement error
                error_log("Failed to prepare database statement: " . $conn->error);
                $_SESSION['message'] = "Failed to prepare database statement: " . $conn->error;
                $_SESSION['message_type'] = "danger";
            }
        }
    }
    
    // Redirect on error/validation/non-critical image failure (POST-Redirect-GET)
    header("Location: municipal-add_announcement.php");
    exit();
}

// Close connection if it was not closed during successful POST redirect
if (isset($conn)) {
    $conn->close();
}

// Re-fetch message to display
$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? null;
unset($_SESSION['message']);
unset($_SESSION['message_type']);

?>

<!DOCTYPE html>
<html lang="en">
<!-- HTML remains the same -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Account - Add Announcement</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts (Updated for Consistency) -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Custom Styles (Matching municipal-announcements.php) -->
    <style>
        /* CSS styles remain the same */
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
        .btn-theme {
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
        
        .form-label {
            font-weight: 600;
            color: #0f5132;
        }
        
        .form-control, .form-select {
            border-radius: 0.25rem;
            padding: 0.5rem 0.75rem;
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
            <h1 class="page-title">Create New Announcement</h1>
            <p class="text-muted mb-4 dashboard-description">
                Fill in the details below to publish a new announcement for the community.
            </p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-body">
                    <!-- enctype="multipart/form-data" is required for file uploads -->
                    <form method="POST" enctype="multipart/form-data" action="municipal-add_announcement.php">
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Announcement Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required maxlength="255">
                        </div>

                        <div class="mb-3">
                            <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="" disabled selected>Select a category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo ucwords($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="content" name="content" rows="8" required></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <!-- Input name is 'image' to match the $_FILES['image'] access -->
                            <label for="image" class="form-label">Image Upload (Optional)</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/png,image/gif">
                            <div class="form-text">Max file size 2MB. Only JPG, PNG, GIF allowed.</div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="municipal-announcements.php" class="btn btn-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-theme">
                                <i class="fas fa-bullhorn me-2"></i> Publish Announcement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar Toggle Logic (Consistent with municipal-announcements.php)
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
        });
    </script>
</body>

</html>     