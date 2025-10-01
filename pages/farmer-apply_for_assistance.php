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
    <title>Farmer Account - Apply for Assistance</title>
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
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            margin: 0;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: #19860f; /* Green theme */
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
            color: #19860f;
            font-weight: 600;
        }

        .sidebar .nav-link:hover:not(.active) {
            background-color: #146c0b; /* Slightly darker green for hover */
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

        .logout-btn {
            background: #ff4b2b; /* Red logout button */
            color: #fff;
            border: none;
            padding: 6px 14px;
            font-size: 14px;
            border-radius: 20px;
            transition: background 0.2s ease, transform 0.2s ease;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: #e04325; /* Darker red on hover */
            transform: translateY(-1px);
        }

        /* Main Content Area */
        main {
            margin-left: 250px; /* Match sidebar width */
            padding: 1rem 2rem 2rem 2rem;
            padding-top: 72px; /* Space for the fixed header */
            background: #f8f9fa;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px; /* Max width for content */
        }

        .page-title {
            font-size: 1.8rem; /* Consistent title size */
            font-weight: 600;
            color: #19860f;
            margin-bottom: 1.5rem; /* Increased margin for better spacing */
            display: flex;
            align-items: center;
        }

        .page-title i {
            margin-right: 15px;
            font-size: 2rem; /* Larger icon for title */
        }

        /* Card Styling */
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); /* More pronounced shadow for cards */
            border: none; /* Remove default border */
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
            justify-content: space-between;
            z-index: 1060;
            border-bottom: 1px solid #ddd;
        }

        .header-brand span {
            font-size: 1rem;
            font-weight: 600;
            color: #19860f;
        }

        /* Form Elements */
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }

        .form-label i {
            margin-right: 8px;
            color: #19860f; /* Green icon for form labels */
        }

        .form-select, .form-control {
            border-radius: 0.4rem; /* Slightly less rounded */
            padding: 0.7rem 1rem; /* Consistent padding */
            border: 1px solid #ced4da;
            transition: all 0.2s ease-in-out;
            font-size: 15px; /* Consistent font size */
        }

        .form-select:focus, .form-control:focus {
            border-color: #19860f;
            box-shadow: 0 0 0 0.2rem rgba(25, 134, 15, 0.2); /* Softer focus shadow */
            outline: none;
        }

        textarea.form-control {
            min-height: 120px; /* Ensure textarea has enough height */
        }

        .form-text {
            font-size: 0.85rem; /* Smaller text for hints */
            color: #6c757d;
        }

        /* Themed Buttons */
        .btn-theme {
            background-color: #19860f;
            color: #fff;
            border: none;
            padding: 10px 22px; /* Slightly larger padding for form buttons */
            border-radius: 0.4rem; /* Consistent border radius */
            transition: all 0.3s ease;
            font-size: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-theme i {
            margin-right: 8px;
        }

        .btn-theme:hover {
            background-color: #157a0d; /* Darker green on hover */
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Custom Info Alert */
        .alert-info-custom {
            background-color: #e6f2e6; /* Light green background */
            color: #157a0d; /* Darker green text */
            border-color: #aed5ae;
            padding: 1rem 1.5rem; /* Consistent padding */
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            font-size: 0.95rem; /* Slightly smaller font for alerts */
        }

        .alert-info-custom i {
            margin-right: 15px;
            font-size: 1.4rem; /* Adjusted icon size */
            color: #19860f; /* Green icon color */
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <a href="ProvincialAgriHome.html" class="header-brand">
            <img src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Department of Agriculture of the Philippines" />
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
                <a href="farmer-apply_for_assistance.php" class="nav-link active">
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
    <div class="card-header card-header-custom d-flex justify-content-end align-items-center">
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <button class="logout-btn" onclick="location.href='farmers-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h1 class="page-title">
                </i> Apply for Assistance
            </h1>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php if ($message_type == 'success'): ?>
                        <i class="fas fa-check-circle me-2"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php endif; ?>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="alert alert-info-custom" role="alert">
                <i class="fas fa-info-circle"></i>
                Please fill in the form below to request support such as seeds, fertilizer, or fuel. Your application will be reviewed by the Provincial Agriculture Office.
            </div>

            <div class="card">
                <div class="card-header">
                    Assistance Request Form
                </div>
                <div class="card-body p-4">
                    <form method="POST" action=""> <!-- Form method set to POST, action to current page -->
                        <div class="mb-4">
                            <label for="assistanceType" class="form-label">
                                <i class="fas fa-hands-helping"></i>Type of Assistance
                            </label>
                            <select class="form-select" id="assistanceType" name="assistanceType" required>
                                <option value="">-- Select Assistance --</option>
                                <option value="Seeds" <?php echo (isset($_POST['assistanceType']) && $_POST['assistanceType'] == 'Seeds') ? 'selected' : ''; ?>>Seeds</option>
                                <option value="Fertilizer" <?php echo (isset($_POST['assistanceType']) && $_POST['assistanceType'] == 'Fertilizer') ? 'selected' : ''; ?>>Fertilizer</option>
                                <option value="Fuel" <?php echo (isset($_POST['assistanceType']) && $_POST['assistanceType'] == 'Fuel') ? 'selected' : ''; ?>>Fuel</option>
                                <option value="Cash Assistance" <?php echo (isset($_POST['assistanceType']) && $_POST['assistanceType'] == 'Cash Assistance') ? 'selected' : ''; ?>>Cash Assistance</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="cropType" class="form-label">
                                <i class="fas fa-leaf"></i>Crop Type (if applicable)
                            </label>
                            <select class="form-select" id="cropType" name="cropType">
                                <option value="">-- Select Crop Type --</option>
                                <option value="Rice" <?php echo (isset($_POST['cropType']) && $_POST['cropType'] == 'Rice') ? 'selected' : ''; ?>>Rice</option>
                                <option value="Corn" <?php echo (isset($_POST['cropType']) && $_POST['cropType'] == 'Corn') ? 'selected' : ''; ?>>Corn</option>
                                <option value="Vegetables" <?php echo (isset($_POST['cropType']) && $_POST['cropType'] == 'Vegetables') ? 'selected' : ''; ?>>Vegetables</option>
                            </select>
                            <small class="form-text text-muted">Specify the crop type this assistance is for (e.g., Rice for seeds).</small>
                        </div>

                        <div class="mb-4">
                            <label for="quantity" class="form-label">
                                <i class="fas fa-boxes"></i>Quantity / Amount Needed
                            </label>
                            <input type="text" class="form-control" id="quantity" name="quantity" placeholder="e.g., 50kg seeds, 10 liters fuel, P5,000 cash" value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>" required />
                            <small class="form-text text-muted">Be specific with quantity and units for faster processing.</small>
                        </div>

                        <div class="mb-4">
                            <label for="remarks" class="form-label">
                                <i class="fas fa-comment-dots"></i>Remarks / Additional Details
                            </label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="5" placeholder="Explain why you need this assistance, how it will be used, and any other relevant information."><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">Provide a clear explanation to support your request.</small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-theme">
                                <i class="fas fa-paper-plane"></i>Submit Request
                            </button>
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