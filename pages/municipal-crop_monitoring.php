<?php
session_start(); // Start the session at the very beginning of the script

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("location: municipal-login.php");
    exit();
}

// Database connection details
$servername = "localhost";
$db_username = "root"; // Your database username
$db_password = "";     // Your database password
$dbname = "cap101"; // Your database name

// Create database connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve the user's name from the session or database
$display_name = 'Mao'; // Fallback name
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($fetched_db_name);
    $stmt->fetch();
    if ($fetched_db_name) {
        $display_name = $fetched_db_name; // Use the name fetched from DB
    }
    $stmt->close();
}

// Fetch crop monitoring data, including photo_path
$crop_monitoring_data = [];
$sql = "SELECT
            ps.id,
            u.name AS farmer_name,
            f.address AS farmer_address, -- Fetched address from 'farmers' table
            ps.crop_identifier,
            ps.status,
            ps.update_date,
            ps.photo_path
        FROM
            planting_status ps
        JOIN
            users u ON ps.user_id = u.user_id
        LEFT JOIN
            farmers f ON u.user_id = f.user_id -- Join with farmers table using user_id
        ORDER BY
            ps.update_date DESC";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $crop_monitoring_data[] = $row;
    }
}

// Fetch unique addresses for the filter dropdown
$unique_addresses = [];
$address_sql = "SELECT DISTINCT address FROM farmers WHERE address IS NOT NULL AND address != '' ORDER BY address ASC";
$address_result = $conn->query($address_sql);

if ($address_result && $address_result->num_rows > 0) {
    while ($row = $address_result->fetch_assoc()) {
        $unique_addresses[] = $row['address'];
    }
}


// Close database connection
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Crop Monitoring - MAO</title>

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
            border: none;
            transition: all 0.3s ease;
        }

        .btn-theme:hover {
            background-color: #146c0b;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        main {
            margin-left: 250px;
            padding: 1rem 2rem 2rem 2rem;
            padding-top: 72px;
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
        }

        .status-planted {
            background-color: #28a745;
            color: #fff;
        }
        .status-harvested {
            background-color: #17a2b8;
            color: #fff;
        }
        .status-pending {
            background-color: #ffc107;
            color: #856404;
        }

        .status-no-update {
            background-color: #dc3545;
            color: #fff;
        }

        .filter-select {
            min-width: 180px;
            border-radius: 4px;
            border: 1px solid #ced4da;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
        }

        .table th,
        .table td {
            vertical-align: middle;
            font-size: 15px;
        }

        .table-bordered {
            border: 1px solid #dee2e6;
        }

        .table-hover tbody tr:hover {
            background-color: #f2f2f2;
        }

        .badge {
            font-size: 0.85em;
            padding: 0.4em 0.6em;
        }

        /* Styles for the modal image */
        #photoModal img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: auto;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
                border-right: none;
                border-bottom: 1px solid #ddd;
            }

            .card-header-custom {
                left: 0;
                top: auto;
                position: relative;
                margin-bottom: 1rem;
            }

            main {
                margin-left: 0;
                padding-top: 1rem;
            }
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
                <a href="municipal-crop_monitoring.php" class="nav-link active">
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
            <h1 class="page-title">Crop Monitoring</h1>
            <p class="text-muted mb-4">Monitor planting updates and crop growth submitted by farmers.</p>

            <!-- Filter Options -->
            <div class="row mb-4 align-items-end">
                <div class="col-md-6">
                    <label for="filterType" class="form-label">Filter by</label>
                    <select class="form-select filter-select" id="filterType" onchange="filterTable()">
                        <option value="all">All</option>
                        <option value="address">Address</option>
                        <?php foreach ($unique_addresses as $address): ?>
                            <option value="<?php echo htmlspecialchars($address); ?>">Address: <?php echo htmlspecialchars($address); ?></option>
                        <?php endforeach; ?>
                        <option value="farmer">Farmer</option>
                        <option value="notUpdated">No Recent Update</option>
                        <option value="status-planted">Status: Planted</option>
                        <option value="status-pending">Status: Pending</option>
                        <option value="status-harvested">Status: Harvested</option>
                    </select>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <button class="btn btn-danger btn-theme" onclick="sendReminders()">
                        <i class="fas fa-bell me-2"></i> Send Reminders to Inactive Farmers
                    </button>
                </div>
            </div>

            <!-- Monitoring Table -->
            <div class="table-responsive card p-3">
                <table class="table table-bordered table-hover bg-white" id="cropMonitoringTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Farmer Name</th>
                            <th>Address</th>
                            <th>Crop</th>
                            <th>Status</th>
                            <th>Last Update</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($crop_monitoring_data)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No crop monitoring data found.</td>
                            </tr>
                        <?php else: ?>
                            <?php $row_number = 1; ?>
                            <?php foreach ($crop_monitoring_data as $data): ?>
                                <?php
                                    $status_class = '';
                                    switch (strtolower($data['status'])) {
                                        case 'planted':
                                            $status_class = 'bg-success status-planted';
                                            break;
                                        case 'pending':
                                            $status_class = 'bg-warning text-dark status-pending';
                                            break;
                                        case 'harvested':
                                            $status_class = 'bg-info status-harvested';
                                            break;
                                        case 'no update':
                                            $status_class = 'bg-danger status-no-update';
                                            break;
                                        default:
                                            $status_class = 'bg-secondary';
                                            break;
                                    }
                                    $last_update_date = new DateTime($data['update_date']);
                                    $current_date = new DateTime();
                                    $interval = $current_date->diff($last_update_date);
                                    $days_since_update = $interval->days;

                                    if ($days_since_update > 30 && strtolower($data['status']) !== 'harvested') {
                                        $status_class = 'bg-danger status-no-update';
                                        $display_status = 'No Update (' . $days_since_update . ' days)';
                                    } else {
                                        $display_status = $data['status'];
                                    }

                                    // Check if photo_path exists and is not empty
                                    $photo_available = !empty($data['photo_path']);
                                ?>
                                <tr>
                                    <td><?php echo $row_number++; ?></td>
                                    <td><?php echo htmlspecialchars($data['farmer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($data['farmer_address']); ?></td>
                                    <td><?php echo htmlspecialchars($data['crop_identifier']); ?></td>
                                    <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($display_status); ?></span></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($data['update_date']))); ?></td>
                                    <td>
                                        <?php if ($photo_available): ?>
                                            <button class="btn btn-sm btn-outline-primary view-photo-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#photoModal"
                                                    data-photo-path="<?php echo htmlspecialchars($data['photo_path']); ?>">
                                                View
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled>No Photo</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoModalLabel">Crop Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="cropPhotoDisplay" src="" alt="Crop Photo" class="img-fluid rounded" />
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function filterTable() {
            const filter = document.getElementById("filterType").value;
            const rows = document.querySelectorAll("#cropMonitoringTable tbody tr");

            rows.forEach((row) => {
                if (row.children.length < 7) return; 

                const farmerName = row.children[1].textContent.toLowerCase();
                const address = row.children[2].textContent.toLowerCase(); // Use the fetched address
                const statusElement = row.children[4].querySelector('.badge');
                const status = statusElement ? statusElement.textContent.toLowerCase() : '';
                const lastUpdate = row.children[5].textContent; 
                const daysSinceUpdate = getDaysSince(lastUpdate);

                let show = true;

                if (filter === "address") {
                    // If "address" is selected, don't filter by a specific address here,
                    // as the individual addresses are now options.
                    // This case might be used if you had a search input for address.
                    // For now, it will show all if 'address' is selected without a specific address option.
                } else if (filter.startsWith("Address: ")) { // Check if the filter is one of the specific addresses
                    const specificAddress = filter.replace("Address: ", "").toLowerCase();
                    show = address.includes(specificAddress);
                } else if (filter === "farmer") {
                    // This example assumes filtering by a specific farmer name part "juan"
                    // In a real application, you'd likely have a search input for the farmer name.
                    show = farmerName.includes("juan"); 
                } else if (filter === "notUpdated") {
                    show = daysSinceUpdate > 30 && !status.includes("harvested"); 
                } else if (filter.startsWith("status-")) {
                    const statusFilter = filter.replace("status-", "");
                    show = status.includes(statusFilter);
                } else if (filter === "all") {
                    show = true; 
                }

                row.style.display = show ? "" : "none";
            });
        }

        function getDaysSince(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diffTime = Math.abs(now - date);
            return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        }

        function sendReminders() {
            alert("Reminders sent to all farmers who haven't updated in over 30 days and whose crops are not harvested.");
            // TODO: Add AJAX call to backend for real reminder functionality
            // This would involve sending user_ids of inactive farmers to a PHP script
            // that handles sending email/SMS reminders.
        }

        // Script to handle the photo modal
        document.addEventListener('DOMContentLoaded', function() {
            filterTable(); // Initial filter call to ensure correct display on page load

            const photoModal = document.getElementById('photoModal');
            photoModal.addEventListener('show.bs.modal', function (event) {
                // Button that triggered the modal
                const button = event.relatedTarget;
                // Extract info from data-photo-path attribute
                const photoPath = button.getAttribute('data-photo-path');
                // Update the modal's content.
                const modalImage = photoModal.querySelector('#cropPhotoDisplay');
                modalImage.src = photoPath; // Set the image source
            });
        });
    </script>
</body>

</html>