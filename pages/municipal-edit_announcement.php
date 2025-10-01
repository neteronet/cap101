<?php
session_start();
// Database connection
$servername = "localhost";
$username = "root"; // Replace with your database username
$password = "";     // Replace with your database password
$dbname = "cap101";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$announcement = null; // Initialize announcement data

// Fetch existing announcement data if an ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $announcement_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($announcement_id) {
        $stmt = $conn->prepare("SELECT id, title, category, content, image_url FROM announcements WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $announcement_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $announcement = $result->fetch_assoc();
            } else {
                $_SESSION['message'] = "Announcement not found.";
                $_SESSION['message_type'] = "danger";
                header("Location: municipal-announcements.php");
                exit();
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare statement for fetching announcement: " . $conn->error);
            $_SESSION['message'] = "Error fetching announcement data.";
            $_SESSION['message_type'] = "danger";
            header("Location: municipal-announcements.php");
            exit();
        }
    } else {
        $_SESSION['message'] = "Invalid announcement ID.";
        $_SESSION['message_type'] = "danger";
        header("Location: municipal-announcements.php");
        exit();
    }
} else {
    // If no ID is provided, redirect back to the announcements list
    $_SESSION['message'] = "No announcement ID provided for editing.";
    $_SESSION['message_type'] = "danger";
    header("Location: municipal-announcements.php");
    exit();
}


// Handle form submission for updating the announcement
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = filter_var($_POST['announcementId'], FILTER_VALIDATE_INT);
    $title = $_POST['announcementTitle'];
    $category = $_POST['announcementCategory'];
    $content = $_POST['announcementContent'];
    $image_url = $_POST['announcementImage']; // Optional, can be empty

    if ($id && $announcement && $id === (int)$announcement['id']) { // Ensure we're updating the correct announcement
        // Prepare an update statement
        $stmt = $conn->prepare("UPDATE announcements SET title = ?, category = ?, content = ?, image_url = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $title, $category, $content, $image_url, $id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Announcement updated successfully!";
            $_SESSION['message_type'] = "success";
            header("Location: municipal-announcements.php"); // Redirect to the main announcements page
            exit();
        } else {
            $_SESSION['message'] = "Error updating announcement: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
            header("Location: municipal-edit_announcement.php?id=" . $id); // Stay on edit page with error
            exit();
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "Invalid request for updating announcement.";
        $_SESSION['message_type'] = "danger";
        header("Location: municipal-announcements.php");
        exit();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Agri - Edit Announcement</title>
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
            justify-content: flex-start; /* Align header content to the left */
            z-index: 1060;
            border-bottom: 1px solid #ddd;
        }

        .header-brand span {
            font-size: 1rem;
            font-weight: 600;
            color: #19860f;
        }

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
            padding: 10px 20px; /* This defines the desired size */
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

        .btn-back-theme {
            background-color: #6c757d; /* Bootstrap secondary gray */
        }

        .btn-back-theme:hover {
            background-color: #5a6268; /* Darker gray on hover */
            color: #fff; /* Ensure text color remains white on hover */
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

        .title-and-button-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem; /* Space between title/button and the form */
        }

        .title-and-button-row .page-title {
            margin-bottom: 0; /* Remove bottom margin from title when in flex row */
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
                <h1 class="page-title"><i class="fas fa-edit me-2"></i>Edit Announcement</h1>
                <!-- Changed classes here -->
                <button type="button" class="btn btn-theme btn-back-theme" onclick="location.href='municipal-announcements.php'">
                    <i class="fas fa-arrow-left me-2"></i>Back to Announcements
                </button>
            </div>
            
            <p class="text-muted mb-4">
                Update the details of this announcement.
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
                    <form id="editAnnouncementForm" method="POST" action="">
                        <input type="hidden" name="announcementId" value="<?php echo htmlspecialchars($announcement['id']); ?>">
                        <div class="mb-3">
                            <label for="announcementTitle" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="announcementTitle" name="announcementTitle" value="<?php echo htmlspecialchars($announcement['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="announcementCategory" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="announcementCategory" name="announcementCategory" required>
                                <option value="">Select Category...</option>
                                <option value="Advisory" <?php echo ($announcement['category'] == 'Advisory') ? 'selected' : ''; ?>>Advisory</option>
                                <option value="Program" <?php echo ($announcement['category'] == 'Program') ? 'selected' : ''; ?>>Program</option>
                                <option value="Alert" <?php echo ($announcement['category'] == 'Alert') ? 'selected' : ''; ?>>Alert</option>
                                <option value="General" <?php echo ($announcement['category'] == 'General') ? 'selected' : ''; ?>>General Updates</option>
                                <option value="Agriculture" <?php echo ($announcement['category'] == 'Agriculture') ? 'selected' : ''; ?>>Agriculture</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="announcementContent" class="form-label">Content <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="announcementContent" name="announcementContent" rows="8" required><?php echo htmlspecialchars($announcement['content']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="announcementImage" class="form-label">Image URL (Optional)</label>
                            <input type="url" class="form-control" id="announcementImage" name="announcementImage" placeholder="e.g., https://via.placeholder.com/600x400/19860f/ffffff?text=Announcement+Image" value="<?php echo htmlspecialchars($announcement['image_url']); ?>">
                            <small class="form-text text-muted">Provide a direct link to an image to include with your announcement.</small>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-theme"><i class="fas fa-save me-2"></i>Update Announcement</button>
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