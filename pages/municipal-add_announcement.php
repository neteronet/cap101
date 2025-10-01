<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("location: municipal-login.php");
    exit();
}

$display_name = $_SESSION['name'] ?? 'Municipal Officer';

$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "cap101";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
} else {
    $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($fetched_db_name);
    $stmt->fetch();
    if ($fetched_db_name) {
        $display_name = $fetched_db_name;
    }
    $stmt->close();
}

// Handle form submission for new announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['announcementTitle'] ?? '';
    $category = $_POST['announcementCategory'] ?? '';
    $content = $_POST['announcementContent'] ?? '';
    $image_url = $_POST['announcementImage'] ?? '';
    $status = 'Published'; // New announcements are published by default

    // Basic validation
    if (empty($title) || empty($category) || empty($content)) {
        $_SESSION['message'] = "Please fill in all required fields (Title, Category, Content).";
        $_SESSION['message_type'] = "danger";
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO announcements (title, category, content, image_url, status) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("sssss", $title, $category, $content, $image_url, $status);

        if ($insert_stmt->execute()) {
            $_SESSION['message'] = "Announcement published successfully!";
            $_SESSION['message_type'] = "success";
            header("Location: municipal-announcement.php"); // Redirect to the announcements list
            exit();
        } else {
            $_SESSION['message'] = "Error publishing announcement: " . $insert_stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $insert_stmt->close();
    }
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
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            margin-bottom: 1rem;
        }

        .sidebar .header-brand img {
            width: 100%;
            max-width: 120px;
            height: auto;
            background: #19860f;
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
            justify-content: flex-end;
            z-index: 1060;
            border-bottom: 1px solid #ddd;
        }

        .header-brand span {
            font-size: 1rem;
            font-weight: 600;
            color: #19860f;
        }

        .logout-btn {
            background: #ff4b2b;
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
        }

        .btn-theme {
            background-color: #19860f;
            color: #fff;
            font-size: 15px;
            padding: 10px 20px;
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

        main {
            margin-left: 250px;
            padding: 1rem 2rem 2rem 2rem;
            padding-top: 72px;
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
    <!-- Sidebar -->
    <nav class="sidebar">
        <a href="municipal-dashboard.php" class="header-brand">
            <img src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Province of Antique" />
            <div>Province of Antique</div>
        </a>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="municipal-dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="municipal-farmer_profiles.php" class="nav-link">
                    <i class="fas fa-users"></i> Farmer Profiles
                </a>
            </li>
            <li class="nav-item">
                <a href="municipal-crop_monitoring.php" class="nav-link">
                    <i class="fas fa-seedling"></i> Crop Monitoring
                </a>
            </li>
            <li class="nav-item">
                <a href="municipal-subsidy_management.php" class="nav-link">
                    <i class="fas fa-hand-holding-usd"></i> Subsidy Management
                </a>
            </li>
            <li class="nav-item">
                <a href="municipal-announcement.php" class="nav-link">
                    <i class="fas fa-bullhorn"></i> Announcements
                </a>
            </li>
            <li class="nav-item">
                <a href="municipal-add_announcement.php" class="nav-link active">
                    <i class="fas fa-newspaper"></i> Publish Announcement
                </a>
            </li>
            <li class="nav-item">
                <a href="municipal-reports_analytics.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i> Reports & Analytics
                </a>
            </li>
            <li class="nav-item">
                <a href="municipal-qrcode_management.php" class="nav-link">
                    <i class="fas fa-qrcode"></i> QR Code Management
                </a>
            </li>
        </ul>
    </nav>

    <!-- Header -->
    <div class="card-header card-header-custom">
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <button class="logout-btn" onclick="location.href='municipal-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container-fluid">
            <h1 class="page-title"><i class="fas fa-plus-circle me-2"></i>Create New Announcement</h1>
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
                    <form id="newAnnouncementForm" method="POST" action="">
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
                        <div class="mb-3">
                            <label for="announcementImage" class="form-label">Image URL (Optional)</label>
                            <input type="url" class="form-control" id="announcementImage" name="announcementImage" placeholder="e.g., https://via.placeholder.com/600x400/19860f/ffffff?text=Announcement+Image">
                            <small class="form-text text-muted">Provide a direct link to an image to include with your announcement.</small>
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