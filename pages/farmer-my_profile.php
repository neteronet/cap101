<?php
session_start(); // Start the session at the very beginning of the script

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("location: farmers-login.php");
    exit();
}

// Initialize variables to hold fetched data
$display_name = 'Farmer'; // Default name for the header
$farmer_data = null;     // Will hold all farmer profile data

$servername = "localhost";
$db_username = "root"; // Your database username
$db_password = "";     // Your database password
$dbname = "cap101"; // Your database name

// Create connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    // In a real application, you might redirect to a friendly error page
    die("A database error occurred. Please try again later.");
}

// --- 1. Fetch user's display name for the header ---
if (isset($_SESSION['user_id'])) {
    $stmt_user = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
    if ($stmt_user) {
        $stmt_user->bind_param("i", $_SESSION['user_id']);
        $stmt_user->execute();
        $stmt_user->bind_result($fetched_db_name);
        $stmt_user->fetch();
        if ($fetched_db_name) {
            $display_name = htmlspecialchars($fetched_db_name);
        }
        $stmt_user->close();
    } else {
        error_log("Failed to prepare user name statement: " . $conn->error);
    }
}

// --- 2. Fetch farmer's profile data ---
if (isset($_SESSION['user_id'])) {
    // Removed 'status' from the SELECT query
    $stmt_farmer = $conn->prepare("SELECT
                                    farmer_id, rsbsa_id, first_name, middle_name, last_name,
                                    address, contact_number, land_details,
                                    age, gender, civil_status, crop
                                   FROM Farmers
                                   WHERE user_id = ?");
    if ($stmt_farmer) {
        $stmt_farmer->bind_param("i", $_SESSION['user_id']);
        $stmt_farmer->execute();
        $result = $stmt_farmer->get_result();
        $farmer_data = $result->fetch_assoc();
        $stmt_farmer->close();

        // If land_details is a JSON string, decode it
        if ($farmer_data && isset($farmer_data['land_details']) && !empty($farmer_data['land_details'])) {
            $farmer_data['land_details_decoded'] = json_decode($farmer_data['land_details'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decoding error for farmer_id " . ($farmer_data['farmer_id'] ?? 'unknown') . ": " . json_last_error_msg() . " Raw data: " . $farmer_data['land_details']);
                $farmer_data['land_details_decoded'] = []; // Fallback to empty array on error
            }
        } else if ($farmer_data) {
             $farmer_data['land_details_decoded'] = []; // Initialize if no land_details or empty string
        }
    } else {
        error_log("Failed to prepare farmer data statement: " . $conn->error);
    }
}

$conn->close();

// Fallback for cases where farmer_data couldn't be fetched or doesn't exist
if (!$farmer_data) {
    $farmer_data = [
        'first_name' => 'N/A',
        'middle_name' => '',
        'last_name' => 'N/A',
        'rsbsa_id' => 'N/A',
        'address' => 'N/A',
        'contact_number' => 'N/A',
        'land_details' => null,
        'land_details_decoded' => [],
        'age' => 'N/A',
        'gender' => 'N/A',
        'civil_status' => 'N/A',
        'crop' => 'N/A'
    ];
}

// Construct full name for display in the profile body
$full_name_profile = htmlspecialchars($farmer_data['first_name'] . ' ' .
                       (!empty($farmer_data['middle_name']) ? substr($farmer_data['middle_name'], 0, 1) . '. ' : '') .
                       $farmer_data['last_name']);

// Use the fetched age, gender, civil_status, and crop directly
$age = htmlspecialchars($farmer_data['age'] ?? 'N/A');
$gender = htmlspecialchars($farmer_data['gender'] ?? 'N/A');
$civil_status = htmlspecialchars($farmer_data['civil_status'] ?? 'N/A');
$crop = htmlspecialchars($farmer_data['crop'] ?? 'N/A');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Profile - Farmer Portal</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

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
            display: flex;
            align-items: center;
            text-decoration: none;
            box-sizing: border-box;
            /* Ensure padding doesn't push it out */
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
            padding: 5px;
            background: #19860f;
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
            /* Align to the right */
            z-index: 1060;
            border-bottom: 1px solid #ddd;
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

        main {
            margin-left: 250px;
            padding: 72px 2rem 2rem 2rem;
            background: #f8f9fa;
            min-height: 100vh;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #19860f;
            margin-bottom: 1rem;
            /* Reduced margin to match dashboard */
        }

        /* General card styling for consistency */
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            /* Consistent bottom margin */
            border: 1px solid #ddd;
            /* Add border for consistency */
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }

        .profile-header img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }

        .profile-header h4 {
            font-size: 1.5rem;
            /* Consistent with dashboard card titles */
            color: #19860f;
            margin-bottom: 0.3rem;
        }

        .info-label {
            font-weight: 600;
            margin-right: 0.5rem;
            color: #555;
            /* Slightly darker for better readability */
        }

        .section-title {
            font-size: 1.25rem;
            /* Consistent with dashboard card titles */
            font-weight: 600;
            color: #19860f;
            margin-top: 1.5rem;
            /* Adjusted for consistency */
            margin-bottom: 1rem;
        }

        /* Adjusting text within cards for consistency */
        .card-body p {
            margin-bottom: 0.5rem;
            /* Consistent spacing for paragraphs */
            font-size: 15px;
            /* Slightly smaller for detailed info */
        }

        .card-body .mb-0 {
            /* For the last paragraph in a section */
            margin-bottom: 0;
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
            <li class="nav-item"><a href="farmer-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="farmer-my_profile.php" class="nav-link active"><i class="fas fa-user-circle"></i> My Profile</a></li>
            <li class="nav-item"><a href="farmer-subsidy_status.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i> Subsidy Status</a></li>
            <li class="nav-item"><a href="farmer-announcement.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li class="nav-item"><a href="farmer-apply_for_assistance.php" class="nav-link"><i class="fas fa-file-invoice"></i> Apply for Assistance</a></li>
            <li class="nav-item"><a href="farmer-planting_status.php" class="nav-link"><i class="fas fa-leaf"></i> Planting Status</a></li>
            <li class="nav-item"><a href="farmer-progress_tracking.php" class="nav-link"><i class="fas fa-chart-line"></i> Progress Tracking</a></li>
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
        <div class="container">
            <h1 class="page-title">My Profile</h1>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="profile-header">
                        <img src="../photos/Avatar.png" alt="Farmer Photo">
                        <div>
                            <h4><?php echo $full_name_profile; ?></h4>
                            <p class="mb-1 text-muted small">RSBSA ID: <strong><?php echo htmlspecialchars($farmer_data['rsbsa_id']); ?></strong></p>
                            <p class="mb-0 text-muted small">Address: <?php echo htmlspecialchars($farmer_data['address']); ?></p>
                            <!-- Removed the Status line here -->
                        </div>
                    </div>

                    <h5 class="section-title mb-3"><i class="fas fa-info-circle me-2"></i>Personal Information</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p><span class="info-label"><i class="fas fa-calendar-alt me-2 text-success"></i>Age:</span> <?php echo $age; ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p><span class="info-label"><i class="fas fa-venus-mars me-2 text-success"></i>Gender:</span> <?php echo $gender; ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p><span class="info-label"><i class="fas fa-phone-alt me-2 text-success"></i>Contact:</span> <?php echo htmlspecialchars($farmer_data['contact_number']); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p><span class="info-label"><i class="fas fa-ring me-2 text-success"></i>Civil Status:</span> <?php echo $civil_status; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <h5 class="section-title"><i class="fas fa-map-marked-alt me-2"></i>Land Details</h5>

            <?php if (!empty($farmer_data['land_details_decoded'])): ?>
                <div class="card">
                    <div class="card-body">
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($farmer_data['land_details_decoded']['location'] ?? 'N/A'); ?></p>
                        <p><strong>Area:</strong> <?php echo htmlspecialchars($farmer_data['land_details_decoded']['size'] ?? 'N/A'); ?></p>
                        <p><strong>Crop:</strong> <?php echo htmlspecialchars($crop); ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <p class="mb-0 text-muted">No land details recorded.</p>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html> 