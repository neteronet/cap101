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
    // Handle error appropriately, e.g., redirect to an error page
    die("Connection failed: " . $conn->connect_error);
}
// Retrieve the user's name from the session or database
$display_name = $_SESSION['name'] ?? 'Municipal Officer'; // Fallback name
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
// Fetch subsidy applications from the database
$applications = [];
$sql = "
SELECT
    aa.application_id,
    u.name AS farmer_name,
    f.address AS farmer_address, -- Fetching address from the 'farmers' table
    aa.assistance_type,
    aa.seed_type,
    aa.seed_quantity,
    aa.engine_type,
    aa.remarks,
    aa.status,
    aa.user_id,
    aa.qr_code_data
FROM assistance_applications aa
JOIN users u ON aa.user_id = u.user_id
LEFT JOIN farmers f ON u.user_id = f.user_id -- Joining with the 'farmers' table
ORDER BY aa.application_date DESC
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
} else {
    error_log("Error fetching applications: " . $conn->error);
}
// Keep the connection open for AJAX updates, or close and reopen in AJAX
// For now, let's keep it open until the HTML is served. AJAX will handle its own connection.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Account - Subsidy Management</title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

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
        }

        .btn-theme:hover {
            background-color: #146c0b;
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

        .status-pending {
            background-color: #ffc107;
            color: #856404;
        }

        .status-approved {
            background-color: #28a745;
            color: #fff;
        }

        .status-rejected {
            background-color: #dc3545;
            color: #fff;
        }

        .table thead th {
            font-size: 14px;
            font-weight: 600;
            color: #555;
            vertical-align: middle;
        }

        .table tbody td {
            font-size: 14px;
            vertical-align: middle;
        }

        /* Adjusted padding for buttons */
        .table .btn-sm {
            font-size: 13px;
            padding: 0.4rem 0.8rem; /* Increased padding */
            margin-bottom: 0.2rem; /* Added margin for spacing between buttons */
        }

        .table .btn-sm:last-child {
            margin-bottom: 0; /* No margin for the last button in a group */
        }


        .badge {
            font-size: 13px;
            font-weight: 500;
            padding: 0.4em 0.6em;
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
                <a href="municipal-crop_monitoring.php" class="nav-link">
                    <i class="fas fa-seedling"></i> Crop Monitoring
                </a>
            </li>
            <li class="nav-item">
                <a href="municipal-subsidy_management.php" class="nav-link active">
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
            <h1 class="page-title">Subsidy Management</h1>
            <p class="text-muted mb-4">Validate and process subsidy requests submitted by farmers.</p>

            <!-- Subsidy Requests Table -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="subsidyTable">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Farmer Name</th>
                                    <th>Address</th>
                                    <th>Assistance Type</th>
                                    <th>Details</th>
                                    <th>Remarks</th> <!-- Added Remarks Column -->
                                    <th>Status</th>
                                    <th>QR Code</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($applications)) : ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No subsidy applications found.</td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($applications as $app) :
                                        // Determine details string
                                        $details = '';
                                        if ($app['assistance_type'] == 'Seeds') {
                                            $details = $app['seed_type'] . ' (' . $app['seed_quantity'] . ')';
                                        } elseif ($app['assistance_type'] == 'Fuel') { // Changed 'Engine' to 'Fuel' based on farmer form
                                            $details = $app['engine_type'];
                                        } elseif ($app['assistance_type'] == 'Cash Assistance') {
                                            $details = '(N/A)'; // Or specify amount if you have it
                                        } else {
                                            $details = '(N/A)'; // For Fertilizer, etc.
                                        }

                                        // Determine status badge class
                                        $statusClass = '';
                                        switch ($app['status']) {
                                            case 'Pending':
                                                $statusClass = 'status-pending';
                                                break;
                                            case 'Approved':
                                                $statusClass = 'status-approved';
                                                break;
                                            case 'Rejected':
                                                $statusClass = 'status-rejected';
                                                break;
                                            default:
                                                $statusClass = 'status-pending'; // Default
                                                break;
                                        }
                                    ?>
                                        <tr id="request-<?php echo htmlspecialchars($app['application_id']); ?>">
                                            <td><?php echo htmlspecialchars($app['application_id']); ?></td>
                                            <td data-farmer-name="<?php echo htmlspecialchars(str_replace(' ', '', $app['farmer_name'])); ?>" data-user-id="<?php echo htmlspecialchars($app['user_id']); ?>">
                                                <?php echo htmlspecialchars($app['farmer_name']); ?>
                                            </td>
                                            <td>
                                                <?php
                                                // Display farmer's address from the 'farmers' table
                                                echo htmlspecialchars($app['farmer_address'] ?? 'N/A');
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($app['assistance_type']); ?></td>
                                            <td><?php echo htmlspecialchars($details); ?></td>
                                            <td>
                                                <?php if (!empty($app['remarks'])) : ?>
                                                    <button class="btn btn-sm btn-info view-remarks-btn" data-bs-toggle="modal" data-bs-target="#remarksModal" data-remarks="<?php echo htmlspecialchars($app['remarks']); ?>">
                                                        <i class="fas fa-eye me-1"></i>View
                                                    </button>
                                                <?php else : ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($app['status']); ?></span></td>
                                            <td id="qr-<?php echo htmlspecialchars($app['application_id']); ?>">
                                                <?php if (!empty($app['qr_code_data'])) : ?>
                                                    <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php echo urlencode($app['qr_code_data']); ?>&size=70x70" alt="QR Code" class="img-fluid">
                                                <?php else : ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($app['status'] == 'Pending') : ?>
                                                    <button class="btn btn-sm btn-success mb-1" onclick="approveRequest(<?php echo htmlspecialchars($app['application_id']); ?>)"><i class="fas fa-check me-1"></i>Approve</button>
                                                    <button class="btn btn-sm btn-danger" onclick="rejectRequest(<?php echo htmlspecialchars($app['application_id']); ?>)"><i class="fas fa-times me-1"></i>Reject</button>
                                                <?php elseif ($app['status'] == 'Approved') : ?>
                                                    <button class="btn btn-sm btn-secondary" disabled><i class="fas fa-check me-1"></i>Approved</button>
                                                <?php elseif ($app['status'] == 'Rejected') : ?>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="sendBackForReview(<?php echo htmlspecialchars($app['application_id']); ?>)"><i class="fas fa-undo me-1"></i>Send Back</button>
                                                <?php endif; ?>
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

    <!-- Remarks Modal -->
    <div class="modal fade" id="remarksModal" tabindex="-1" aria-labelledby="remarksModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="remarksModalLabel"><i class="fas fa-comment-dots me-2"></i>Farmer Remarks</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="modalRemarksContent" class="lead"></p>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Event listener for the remarks modal
            const remarksModal = document.getElementById('remarksModal');
            remarksModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget; // Button that triggered the modal
                const remarks = button.getAttribute('data-remarks'); // Extract info from data-remarks attribute
                const modalRemarksContent = remarksModal.querySelector('#modalRemarksContent');
                modalRemarksContent.textContent = remarks;
            });
        });

        function approveRequest(id) {
            const row = document.getElementById(`request-${id}`);
            if (!row) {
                console.error(`Row with ID 'request-${id}' not found.`);
                return;
            }

            const farmerNameElement = row.querySelector('td[data-farmer-name]');
            const farmerNameClean = farmerNameElement ? farmerNameElement.dataset.farmerName : `UnknownFarmer${id}`;
            const farmerUserId = farmerNameElement ? farmerNameElement.dataset.userId : `N/A`;

            const assistanceType = row.children[3].textContent;
            const assistanceDetails = row.children[4].textContent;
            const farmerAddress = row.children[2].textContent;

            // Generate QR code data
            const qrData = `AppID:${id}|UserID:${farmerUserId}|FarmerName:${farmerNameClean}|Address:${farmerAddress.replace(/[^\w\s]/gi, '').replace(/\s+/g, '')}|Assistance:${assistanceType.replace(/\s+/g, '')}|Details:${assistanceDetails.replace(/[^\w\s]/gi, '').replace(/\s+/g, '')}|Status:Approved`;

            // AJAX call to update the database
            fetch('municipal-update_subsidy_status.php', { // Create this PHP file
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        application_id: id,
                        status: 'Approved',
                        qr_code_data: qrData // Send QR data to be stored in DB
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const statusCell = row.children[6]; // Adjusted index for new 'Remarks' column
                        const qrCell = row.children[7]; // Adjusted index
                        const actionCell = row.children[8]; // Adjusted index

                        statusCell.innerHTML = '<span class="badge status-approved">Approved</span>';
                        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(qrData)}&size=70x70`;
                        qrCell.innerHTML = `<img src="${qrUrl}" alt="QR Code" class="img-fluid">`;
                        actionCell.innerHTML = '<button class="btn btn-sm btn-secondary" disabled><i class="fas fa-check me-1"></i>Approved</button>';

                        alert(`Subsidy request ${id} approved and QR code generated.`);
                    } else {
                        console.error('DB update failed:', data.message);
                        alert('Failed to update subsidy status in database: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error updating DB:', error);
                    alert('An error occurred during database update.');
                });
        }

        function rejectRequest(id) {
            const row = document.getElementById(`request-${id}`);
            if (!row) {
                console.error(`Row with ID 'request-${id}' not found.`);
                return;
            }

            // AJAX call to update the database
            fetch('municipal-update_subsidy_status.php', { // Create this PHP file
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        application_id: id,
                        status: 'Rejected',
                        qr_code_data: null // Clear QR data on rejection
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const statusCell = row.children[6]; // Adjusted index
                        const qrCell = row.children[7]; // Adjusted index
                        const actionCell = row.children[8]; // Adjusted index

                        statusCell.innerHTML = '<span class="badge status-rejected">Rejected</span>';
                        qrCell.textContent = '—'; // Clear QR code
                        actionCell.innerHTML = `<button class="btn btn-sm btn-outline-primary" onclick="sendBackForReview(${id})"><i class="fas fa-undo me-1"></i>Send Back</button>`;

                        alert(`Subsidy request ${id} rejected.`);
                    } else {
                        console.error('DB update failed:', data.message);
                        alert('Failed to update subsidy status in database: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error updating DB:', error);
                    alert('An error occurred during database update.');
                });
        }

        function sendBackForReview(id) {
            const row = document.getElementById(`request-${id}`);
            if (!row) {
                console.error(`Row with ID 'request-${id}' not found.`);
                return;
            }

            // AJAX call to update the database
            fetch('municipal-update_subsidy_status.php', { // Create this PHP file
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        application_id: id,
                        status: 'Pending',
                        qr_code_data: null // Clear QR data when sending back to pending
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const statusCell = row.children[6]; // Adjusted index
                        const qrCell = row.children[7]; // Adjusted index
                        const actionCell = row.children[8]; // Adjusted index

                        statusCell.innerHTML = '<span class="badge status-pending">Pending</span>';
                        qrCell.textContent = '—';
                        actionCell.innerHTML = `
                            <button class="btn btn-sm btn-success mb-1" onclick="approveRequest(${id})"><i class="fas fa-check me-1"></i>Approve</button>
                            <button class="btn btn-sm btn-danger" onclick="rejectRequest(${id})"><i class="fas fa-times me-1"></i>Reject</button>
                        `;

                        alert(`Subsidy request ${id} returned to pending for review.`);
                    } else {
                        console.error('DB update failed:', data.message);
                        alert('Failed to update subsidy status in database: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error updating DB:', error);
                    alert('An error occurred during database update.');
                });
        }
    </script>
</body>
</html>