<?php
session_start();
include '../includes/connection.php'; // Ensure this path is correct

// Redirect if user_id is not set or not an integer
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: municipal-login.php");
    exit();
}

// START: INSERT CODE for File Upload Handling
$target_dir = "../uploads/announcements/"; // Define target directory relative to this script's location
$uploaded_image_path = ""; // Initialize the variable for the uploaded file path

// Check for file upload before the main POST check
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['announcementImages']) && $_FILES['announcementImages']['error'] == 0) {
    
    // Check if the uploads directory exists, if not, create it
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = basename($_FILES["announcementImages"]["name"]);
    // Create a unique file name using current timestamp and sanitizing the original name
    $unique_file_name = time() . "_" . preg_replace("/[^A-Za-z0-9.]/", "_", $file_name); 
    $target_file = $target_dir . $unique_file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Simple file type validation
    $uploadOk = 1;
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        $_SESSION['message'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed for the image.";
        $_SESSION['message_type'] = "danger";
        $uploadOk = 0;
    }

    if ($uploadOk == 1 && move_uploaded_file($_FILES["announcementImages"]["tmp_name"], $target_file)) {
        // File successfully uploaded. Store the path relative to the site root for DB
        $uploaded_image_path = "uploads/announcements/" . $unique_file_name; 
    } else if ($uploadOk == 1) {
        // Handle move error (if validation passed but move failed)
        $_SESSION['message'] = "Sorry, there was an error uploading your file.";
        $_SESSION['message_type'] = "danger";
        // To prevent insertion, we can exit or set a flag, but for now, we let the logic flow.
    }
}
// END: INSERT CODE for File Upload Handling


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['announcementTitle'];
    $category = $_POST['announcementCategory'];
    $content = $_POST['announcementContent'];
    $image_url = $_POST['announcementImage']; // This line is kept but $_POST['announcementImage'] will be empty due to input type change

    // START: INSERT/APPEND line to use uploaded path
    if (!empty($uploaded_image_path)) {
        $image_url = $uploaded_image_path; // Overwrite $image_url with the file path
    } else {
         // If no file was uploaded, $image_url is empty string, which is fine for the optional field
         $image_url = ''; 
    }
    // END: INSERT/APPEND line to use uploaded path

    // Prepare an insert statement (MODIFIED: changed column name from 'image_url' to 'images')
    $stmt = $conn->prepare("INSERT INTO announcements (title, category, content, images, publish_date) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $title, $category, $content, $image_url);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Announcement published successfully!";
        $_SESSION['message_type'] = "success";
        // Redirect to prevent form resubmission
        header("Location: municipal-add_announcement.php");
        exit();
    } else {
        $_SESSION['message'] = "Error publishing announcement: " . $stmt->error;
        $_SESSION['message_type'] = "danger";
        header("Location: municipal-add_announcement.php");
        exit();
    }

    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Agri - Add Announcement</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <!-- Custom Styles (re-use from municipal-announcement.php, or link a shared CSS file) -->
    <style>
        body {
            font-family: "Poppins", sans-serif;
            background: #f8f9fa;
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            margin: 0;
        }

        .card-header-custom {
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background-color: #fff;
            color: #19860f;
            padding: 0 1.25rem;
            font-weight: 500;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            /* Align header content to the left */
            z-index: 1060;
            border-bottom: 1px solid #ddd;
        }

        .header-brand span {
            font-size: 1rem;
            font-weight: 600;
            color: #19860f;
        }

        /* Original btn-sm-custom (no longer used for back button, but kept if other elements use it) */
        .btn-sm-custom {
            padding: 6px 14px;
            font-size: 14px;
            border-radius: 4px;
            background-color: #6c757d;
            color: #fff;
            border: none;
            transition: background-color 0.2s ease;
        }

        .btn-sm-custom:hover {
            background-color: #5a6268;
            color: #fff;
        }

        .btn-theme {
            background-color: #19860f;
            color: #fff;
            font-size: 15px;
            padding: 10px 20px;
            /* This defines the desired size */
            border-radius: 4px;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-theme:hover {
            background-color: #146c0b;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* New class for the back button, based on btn-theme but with different color */
        .btn-back-theme {
            background-color: #6c757d;
            /* Bootstrap secondary gray */
        }

        .btn-back-theme:hover {
            background-color: #5a6268;
            /* Darker gray on hover */
            color: #fff;
            /* Ensure text color remains white on hover */
        }


        main {
            padding: 1rem 2rem 2rem 2rem;
            padding-top: 22px;
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

        /* Adjust margin for the button group at the top */
        .title-and-button-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            /* Space between title/button and the form */
        }

        .title-and-button-row .page-title {
            margin-bottom: 0;
            /* Remove bottom margin from title when in flex row */
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
    </style>
</head>

<body>
    <!-- Main Content -->
    <main>
        <div class="container-fluid">
            <!-- New row for title and back button -->
            <div class="title-and-button-row">
                <h1 class="page-title"><i class="fas fa-bullhorn me-3"></i>Create New Announcement</h1>
                <!-- Changed classes here -->
                <button type="button" class="btn btn-theme btn-back-theme" onclick="location.href='municipal-announcements.php'">
                    <i class="fas fa-arrow-left me-2"></i>Back to Announcements
                </button>
            </div>

            <p class="text-muted mb-4">
                Fill out the form below to publish a new announcement for farmers.
            </p>

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
                    <h5 class="card-title mb-4">Announcement Details</h5>
                    <!-- MODIFIED: Added enctype for file uploads -->
                    <form id="newAnnouncementForm" method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="announcementTitle" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="announcementTitle" name="announcementTitle" required>
                        </div>
                        <div class="mb-3">
                            <label for="announcementCategory" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="announcementCategory" name="announcementCategory" required>
                                <option value="">Select Category...</option>
                                <option value="Advisory">Advisory</option>
                                <option value="Program">Program</option>
                                <option value="Alert">Alert</option>
                                <option value="General">General Updates</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="announcementContent" class="form-label">Content <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="announcementContent" name="announcementContent" rows="8" required></textarea>
                        </div>
                        <!-- MODIFIED: Changed input type and name for file upload, and updated label text -->
                        <div class="mb-3">
                            <label for="announcementImage" class="form-label">Add Image (Optional)</label>
                            <input type="file" class="form-control" id="announcementImage" name="announcementImages" accept="image/*">
                            <small class="form-text text-muted">Select an image file to include with your announcement.</small>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-theme"><i class="fas fa-paper-plane me-2"></i>Publish Announcement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
