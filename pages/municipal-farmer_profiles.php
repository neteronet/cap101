<?php
session_start();

// Database connection details
$servername = "localhost";
$username_db = "root"; // Replace with your database username
$password_db = "";     // Replace with your database password
$dbname = "cap101"; // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Check connection
if ($conn->connect_error) {
    // Log error or display a generic message, but don't expose database details
    error_log("Database connection failed: " . $conn->connect_error);
    // You might want to redirect to an error page or show a friendly message
    die("Error connecting to the database. Please try again later."); // Critical error, stop execution
}

// Check if user is logged in and is a 'mao' user type
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'mao') {
    header("location: municipal-login.php"); // Redirect to MAO login page
    exit();
}

$display_name = "Guest"; // Default display name

// Fetch the display name from the database for the logged-in user
// Only fetch if the connection is successful and user_id is set
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($fetched_db_name);
        $stmt->fetch();
        if ($fetched_db_name) {
            $display_name = $fetched_db_name; // Use the name fetched from DB
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare statement for fetching user name: " . $conn->error);
    }
}


// Fetch farmer profiles from the database
$farmers = [];
// It's good practice to check if the query was successful
$sql = "SELECT farmer_id, first_name, middle_name, last_name, address, contact_number, land_details, status, age, gender, civil_status, crop
        FROM farmers
        ORDER BY last_name ASC";
$result = $conn->query($sql);

if ($result) { // Check if query executed successfully
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $farmers[] = $row;
        }
    }
    $result->free(); // Free the result set
} else {
    error_log("Error fetching farmer profiles: " . $conn->error);
    // Optionally, display a user-friendly message or redirect
}

$conn->close(); // Close the connection ONLY AFTER all queries are done
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Account - Farmer Profiles</title>

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
            justify-content: space-between;
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
        }

        .btn-theme:hover {
            background-color: #146c0b;
        }

        main {
            margin-left: 250px;
            padding: 1rem 2rem 2rem 2rem;
            padding-top: 72px; /* Adjust for fixed header */
            background: #f8f9fa;
            min-height: 100vh;
        }

        .container {
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

        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.4rem;
            font-size: 13px;
            font-weight: 500;
            text-transform: capitalize; /* Make status look nicer */
        }

        .status-verified {
            background-color: #28a745; /* Success green */
            color: #fff;
        }

        .status-pending {
            background-color: #ffc107; /* Warning yellow */
            color: #856404;
        }

        .table thead th {
            background-color: #f2f2f2;
            color: #555;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }

        .table tbody tr:hover {
            background-color: #f0f8f0;
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
                <a href="municipal-farmer_profiles.php" class="nav-link active">
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
                <a href="municipal-announcements.php" class="nav-link">
                    <i class="fas fa-bullhorn"></i> Announcements
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
    <div class="card-header card-header-custom d-flex justify-content-end align-items-center">
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <button class="logout-btn" onclick="location.href='municipal-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h1 class="page-title">Farmer Profiles</h1>
            <p class="text-muted mb-4">View and manage registered farmer records.</p>

            <!-- Search Bar -->
            <div class="input-group mb-3">
                <input type="text" class="form-control" placeholder="Search by name, barangay, or ID..." id="searchInput">
                <button class="btn btn-theme" type="button" onclick="searchFarmers()"><i class="fas fa-search me-1"></i> Search</button>
            </div>

            <!-- Farmer Table -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-table me-2"></i>Registered Farmers</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover bg-white">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Full Name</th>
                                    <th>Barangay</th>
                                    <th>Contact</th>
                                    <th>Farm Size (ha)</th>
                                    <th>Crop Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="farmerTableBody">
                                <?php if (empty($farmers)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No farmer profiles found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($farmers as $farmer): ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($farmer['first_name'] . ' ' . (!empty($farmer['middle_name']) ? substr($farmer['middle_name'], 0, 1) . '. ' : '') . $farmer['last_name']); ?></td>
                                            <td>
                                                <?php
                                                    $address_parts = explode(',', $farmer['address']);
                                                    // This assumes barangay is always the first part and that 'address' is not null or malformed.
                                                    // A more robust solution might parse the address more carefully or have a dedicated 'barangay' column.
                                                    echo htmlspecialchars(trim($address_parts[0] ?? 'N/A'));
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($farmer['contact_number']); ?></td>
                                            <td>
                                                <?php
                                                    $land_details = json_decode($farmer['land_details'], true);
                                                    echo htmlspecialchars($land_details['size'] ?? 'N/A');
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($farmer['crop']); ?></td>
                                            <td>
                                                <?php
                                                    $status_class = ($farmer['status'] == 'verified') ? 'status-verified' : 'status-pending';
                                                    echo '<span class="status-badge ' . $status_class . '">' . htmlspecialchars($farmer['status']) . '</span>';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function searchFarmers() {
        const input = document.getElementById("searchInput").value.toLowerCase();
        const rows = document.querySelectorAll("#farmerTableBody tr");

        rows.forEach(row => {
            // Get all text content from the row for searching
            const rowText = row.textContent.toLowerCase();
            row.style.display = rowText.includes(input) ? "" : "none";
        });
    }
    </script>

</body>
</html>