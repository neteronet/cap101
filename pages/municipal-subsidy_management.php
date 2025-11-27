<?php
session_start();

include '../includes/connection.php'; // Ensure this path is correct

// --- IMPROVEMENT 1: Robust Connection Check ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? "Connection object not set"));
    // Redirect to login on critical error
    header("location: municipal-login.php");
    exit();
}

// Redirect if user_id is not set or not an integer
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: municipal-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'MAO User'; // Better default fallback
$is_mao = false; // Flag for explicit MAO check

// --- IMPROVEMENT 2 & 3: Fetch Name AND User Type for Security Check ---
$stmt_name = $conn->prepare("SELECT name, user_type FROM users WHERE user_id = ?");
if ($stmt_name) {
    $stmt_name->bind_param("i", $user_id);
    $stmt_name->execute();
    $stmt_name->bind_result($db_name, $db_user_type);
    $stmt_name->fetch();
    $stmt_name->close();

    if ($db_name) {
        $display_name = htmlspecialchars($db_name); // Sanitize immediately
    }

    // --- Explicit MAO Authorization Check ---
    if ($db_user_type === 'mao') {
        $is_mao = true;
    } else {
        // If not MAO, destroy session and redirect
        session_destroy();
        header("location: municipal-login.php");
        exit();
    }
} else {
    error_log("Failed to prepare statement for user name/type: " . $conn->error);
    // Treat preparation failure as a security risk/critical error
    session_destroy();
    header("location: municipal-login.php");
    exit();
}

// --- PAGINATION LOGIC START ---

// 1. Set records per page
$records_per_page = 10;

// 2. Determine current page number
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $page = (int) $_GET['page'];
} else {
    $page = 1;
}
if ($page < 1) $page = 1;

// 3. Calculate offset
$offset = ($page - 1) * $records_per_page;

// 4. Get total number of records for pagination calculations
$total_pages_sql = "SELECT COUNT(*) FROM assistance_applications";
$result_count = $conn->query($total_pages_sql);
$total_rows = $result_count->fetch_array()[0];
$total_pages = ceil($total_rows / $records_per_page);

// --- FETCHING LOGIC (With LIMIT) ---

$applications = [];
// Assuming aa.user_id is the correct foreign key for the farmer, as per context.
// Added LIMIT and OFFSET to the query
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
    aa.user_id
FROM assistance_applications aa
JOIN users u ON aa.user_id = u.user_id
LEFT JOIN farmers f ON u.user_id = f.user_id -- Joining with the 'farmers' table
ORDER BY aa.application_date DESC
LIMIT $offset, $records_per_page
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
} else {
    error_log("Error fetching applications: " . $conn->error);
}

// --- END: FETCHING LOGIC ---

// Note: Closing the connection is moved to the end of the file.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Account - Subsidy Management</title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Fonts (Updated to match farmer-dashboard) -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Custom Styles (Copied and modified from farmer-dashboard.php for consistency) -->
    <style>
        body {
            /* MODIFIED: Changed font-family to Poppins for body/content text */
            font-family: "Poppins", sans-serif;
            background: #f8f9fa;
            font-size: 16px;
            line-height: 1.6;
            color: #212529;
            margin: 0;
        }

        /* --- Sidebar Styles (Consistent Design) --- */
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

        /* --- Fixed Top Header (Consistent Design) --- */
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

        /* --- Main Content Area (Consistent Design) --- */
        main {
            margin-left: 250px;
            padding: 72px 2rem 2rem 2rem; /* Adjusted padding-top for fixed header */
            background: #f8f9fa;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        main.collapsed {
            margin-left: 0;
        }
        
        .page-title {
            font-family: "Be Vietnam Pro", sans-serif;
            color: #0f5132;
            font-size: 1.5rem; 
            font-weight: 600; 
            margin-bottom: 0.5rem;
        }
        
        .dashboard-description {
            font-size: 0.875rem; /* 14px */
        }

        .card-title {
            font-family: "Be Vietnam Pro", sans-serif;
            color: #0f5132;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        
        .card-text, 
        .card-body p:not(.card-title), 
        .list-unstyled li {
            font-size: 0.9375rem; /* ~15px */
        }
        
        .btn-theme {
            background-color: #19860f;
            color: #fff;
            border-color: #19860f;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .btn-theme:hover {
            background-color: #146c0b;
            border-color: #146c0b;
            color: #fff;
        }
        
        /* Table and Badge styles */
        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.4rem;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .status-pending {
            background-color: #ffc107 !important; /* Warning */
            color: #664d03 !important;
        }

        .status-approved {
            background-color: #198754 !important; /* Success */
            color: #fff !important;
        }

        .status-rejected {
            background-color: #dc3545 !important; /* Danger */
            color: #fff !important;
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

        .table .btn-sm {
            font-size: 13px;
            padding: 0.4rem 0.8rem;
            margin-bottom: 0.2rem;
            display: inline-block;
        }

        /* Adjusted padding for icon-only buttons */
        .table .btn-sm i:not(.fa-comment-dots) { 
            margin-right: 0 !important;
        }
        .table .btn-sm:not(.view-remarks-btn) { 
            padding: 0.4rem 0.6rem; /* Slightly smaller padding for icon-only */
        }

        .table .btn-sm:last-child {
            margin-bottom: 0;
        }

        .pagination .page-link {
            color: #19860f;
            border-color: #dee2e6;
            font-family: "Be Vietnam Pro", sans-serif;
        }
        
        .pagination .page-link:hover {
            color: #146c0b;
            background-color: #e9ecef;
            border-color: #dee2e6;
        }

        .pagination .page-item.active .page-link {
            background-color: #19860f;
            border-color: #19860f;
            color: #fff;
        }

        .pagination .page-item.disabled .page-link {
            color: #6c757d;
        }
        
        #sidebarToggleBtn {
            color: #0f5132;
        }

        #sidebarToggleBtn:hover {
            color: #146c0b;
        }
    </style>
</head>
<body>
    <!-- Sidebar (Consistent Design) -->
    <nav class="sidebar">
        <!-- Logo and Text (MAO Branding, New Style) -->
        <a href="municipal-dashboard.php" class="header-brand">
            <img src="../photos/logo.png"/>
            <div>Agriconnect</div>
        </a>

        <!-- Menu Label (New Style) -->
        <div class="sidebar-menu-label">Main Menu</div>

        <!-- Navigation Links (MAO Links, New Style) -->
        <ul class="nav flex-column">
            <li class="nav-item"><a href="municipal-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="municipal-subsidy_management.php" class="nav-link active"><i class="fas fa-hand-holding-usd"></i> Subsidy Management</a></li>
            <li class="nav-item"><a href="municipal-qrcode_management.php" class="nav-link"><i class="fas fa-qrcode"></i> QR Code Management</a></li>
            <li class="nav-item"><a href="municipal-crop_monitoring.php" class="nav-link"><i class="fas fa-seedling"></i> Crop Monitoring</a></li>
            <li class="nav-item"><a href="municipal-reports_analytics.php" class="nav-link"><i class="fas fa-chart-line"></i> Reports & Analytics</a></li>
            <li class="nav-item"><a href="municipal-farmer_profiles.php" class="nav-link"><i class="fas fa-users"></i> Farmer Profiles</a></li>
            <li class="nav-item"><a href="municipal-announcements.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
        </ul>
        
        <!-- Logout Section (New Style) -->
        <div class="sidebar-logout">
            <a href="municipal-logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Header (Consistent Design) -->
    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
        <!-- Sidebar Toggle Button -->
        <button id="sidebarToggleBtn" class="btn btn-link p-0 text-dark" title="Toggle Sidebar" style="font-size: 1.5rem;">
            <i class="fas fa-bars"></i>
        </button>
        <!-- Greeting -->
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <!-- Page Title (Consistent Style) -->
            <h1 class="page-title">Subsidy Management</h1>
            <!-- Page Description (Consistent Style) -->
            <p class="text-muted mb-4 dashboard-description">Validate and process subsidy requests submitted by farmers.</p>

            <!-- Subsidy Requests Table -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="subsidyTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Farmer Name</th>
                                    <th>Address</th>
                                    <th>Assistance Type</th>
                                    <th>Details</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($applications)) : ?>
                                    <tr>
                                        <!-- Colspan is 6 -->
                                        <td colspan="6" class="text-center">No subsidy applications found.</td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($applications as $app) :
                                        
                                        // Determine details string, using the assistance_type if no specific detail is found.
                                        $details = '';
                                        $assistanceType = htmlspecialchars($app['assistance_type']);

                                        if ($assistanceType == 'Seeds') {
                                            $details = htmlspecialchars($app['seed_type'] ?? 'N/A') . ' (' . htmlspecialchars($app['seed_quantity'] ?? 'N/A') . ')';
                                        } elseif ($assistanceType == 'Fuel') { 
                                            $details = htmlspecialchars($app['engine_type'] ?? 'N/A');
                                        } elseif ($assistanceType == 'Cash Assistance') {
                                            $details = 'Cash Request'; 
                                        } else {
                                            // Fallback for Fertilizer, Tools, etc.
                                            $details = $assistanceType . ' Request'; 
                                        }

                                        // Determine status badge class and icon
                                        $statusClass = '';
                                        $statusIcon = '';
                                        switch ($app['status']) {
                                            case 'Pending':
                                                $statusClass = 'status-pending';
                                                $statusIcon = '<i class="fas fa-clock me-1" aria-hidden="true"></i>';
                                                break;
                                            case 'Approved':
                                                $statusClass = 'status-approved';
                                                $statusIcon = '<i class="fas fa-check me-1" aria-hidden="true"></i>';
                                                break;
                                            case 'Rejected':
                                                $statusClass = 'status-rejected';
                                                $statusIcon = '<i class="fas fa-times me-1" aria-hidden="true"></i>';
                                                break;
                                            default:
                                                $statusClass = 'status-pending'; // Default
                                                $statusIcon = '<i class="fas fa-clock me-1" aria-hidden="true"></i>';
                                                break;
                                        }
                                    ?>
                                        <tr id="request-<?php echo htmlspecialchars($app['application_id']); ?>">
                                            <td data-user-id="<?php echo htmlspecialchars($app['user_id']); ?>">
                                                <?php echo htmlspecialchars($app['farmer_name']); ?>
                                            </td>
                                            <td>
                                                <?php
                                                // Display farmer's address from the 'farmers' table
                                                echo htmlspecialchars($app['farmer_address'] ?? 'N/A');
                                                ?>
                                            </td>
                                            <td><?php echo $assistanceType; ?></td>
                                            <td><?php echo $details; ?></td>
                                            <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusIcon . htmlspecialchars($app['status']); ?></span></td>
                                            <td>
                                                <!-- View Remarks Button (Icon-only) -->
                                                <?php if (!empty($app['remarks'])) : ?>
                                                    <button class="btn btn-sm btn-info view-remarks-btn mb-1" data-bs-toggle="modal" data-bs-target="#remarksModal" data-remarks="<?php echo htmlspecialchars($app['remarks']); ?>" aria-label="View remarks" title="View remarks">
                                                        <i class="fas fa-comment-dots" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <!-- Action Buttons (Icon-only) -->
                                                <?php if ($app['status'] == 'Pending') : ?>
                                                    <button class="btn btn-sm btn-success mb-1" onclick="approveRequest(<?php echo htmlspecialchars($app['application_id']); ?>)" title="Approve Request" aria-label="Approve Request"><i class="fas fa-check"></i></button>
                                                    <button class="btn btn-sm btn-danger mb-1" onclick="rejectRequest(<?php echo htmlspecialchars($app['application_id']); ?>)" title="Reject Request" aria-label="Reject Request"><i class="fas fa-times"></i></button>
                                                <?php elseif ($app['status'] == 'Approved') : ?>
                                                    <!-- No action buttons for already approved requests (remarks button remains if present) -->
                                                <?php elseif ($app['status'] == 'Rejected') : ?>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="sendBackForReview(<?php echo htmlspecialchars($app['application_id']); ?>)" title="Send Back for Review" aria-label="Send Back for Review"><i class="fas fa-undo"></i></button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION CONTROLS -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Subsidy application pages" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <!-- Previous Button -->
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo ($page > 1) ? '?page=' . ($page - 1) : '#'; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&lt;</span>
                                </a>
                            </li>

                            <!-- Page Numbers -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next Button -->
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo ($page < $total_pages) ? '?page=' . ($page + 1) : '#'; ?>" aria-label="Next">
                                    <span aria-hidden="true">&gt;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <div class="text-center text-muted small">
                        Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_rows; ?> total applications)
                    </div>
                    <?php endif; ?>
                    <!-- END PAGINATION CONTROLS -->

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

        // UPDATED COLUMN INDICES due to removal of 'ID' column
        // New indices: Farmer Name=0, Address=1, Assistance Type=2, Details=3, Status=4, Actions=5
        const STATUS_CELL_INDEX = 4;
        const ACTION_CELL_INDEX = 5;

        function approveRequest(id) {
            const row = document.getElementById(`request-${id}`);
            if (!row) {
                console.error(`Row with ID 'request-${id}' not found.`);
                return;
            }

            const farmerName = row.children[0].textContent.trim();

            // AJAX call to update the database
            fetch('municipal-update_subsidy_status.php', { 
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        application_id: id,
                        status: 'Approved',
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const statusCell = row.children[STATUS_CELL_INDEX];
                        const actionCell = row.children[ACTION_CELL_INDEX];

                        statusCell.innerHTML = '<span class="badge status-approved"><i class="fas fa-check me-1"></i>Approved</span>';
                        // Keep only the Remarks button (if present) and remove other action buttons for approved requests
                        let remarksBtnHTML = '';
                        const existingRemarksBtn = actionCell.querySelector('.view-remarks-btn') || actionCell.querySelector('.btn-info');
                        if (existingRemarksBtn) {
                            remarksBtnHTML = existingRemarksBtn.outerHTML + ' ';
                        }

                        actionCell.innerHTML = remarksBtnHTML;

                        alert(`Subsidy request '${farmerName}' approved.`);
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

            const farmerName = row.children[0].textContent.trim();

            // AJAX call to update the database
            fetch('municipal-update_subsidy_status.php', { 
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        application_id: id,
                        status: 'Rejected',
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const statusCell = row.children[STATUS_CELL_INDEX];
                        const actionCell = row.children[ACTION_CELL_INDEX];

                        statusCell.innerHTML = '<span class="badge status-rejected"><i class="fas fa-times me-1"></i>Rejected</span>';
                        
                        // Keep Remarks button if it exists
                        let remarksBtnHTML = '';
                        const existingRemarksBtn = actionCell.querySelector('.view-remarks-btn') || actionCell.querySelector('.btn-info');
                        if(existingRemarksBtn) {
                            remarksBtnHTML = existingRemarksBtn.outerHTML + ' ';
                        }

                        // IMPROVEMENT: Changed to icon-only button with title attribute
                        actionCell.innerHTML = remarksBtnHTML + `<button class="btn btn-sm btn-outline-primary" onclick="sendBackForReview(${id})" title="Send Back for Review" aria-label="Send Back for Review"><i class="fas fa-undo"></i></button>`;

                        alert(`Subsidy request '${farmerName}' rejected.`);
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

            const farmerName = row.children[0].textContent.trim();

            // AJAX call to update the database
            fetch('municipal-update_subsidy_status.php', { 
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        application_id: id,
                        status: 'Pending',
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const statusCell = row.children[STATUS_CELL_INDEX];
                        const actionCell = row.children[ACTION_CELL_INDEX];

                        statusCell.innerHTML = '<span class="badge status-pending"><i class="fas fa-clock me-1"></i>Pending</span>';
                        
                         // Keep Remarks button if it exists
                        let remarksBtnHTML = '';
                        const existingRemarksBtn = actionCell.querySelector('.view-remarks-btn') || actionCell.querySelector('.btn-info');
                        if(existingRemarksBtn) {
                            remarksBtnHTML = existingRemarksBtn.outerHTML + ' ';
                        }

                        // IMPROVEMENT: Changed to icon-only buttons with title attribute
                        actionCell.innerHTML = remarksBtnHTML + `
                            <button class="btn btn-sm btn-success mb-1" onclick="approveRequest(${id})" title="Approve Request" aria-label="Approve Request"><i class="fas fa-check"></i></button>
                            <button class="btn btn-sm btn-danger mb-1" onclick="rejectRequest(${id})" title="Reject Request" aria-label="Reject Request"><i class="fas fa-times"></i></button>
                        `;

                        alert(`Subsidy request '${farmerName}' returned to pending for review.`);
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
        
        // --- JavaScript for Sidebar Toggle (Consistent Design) ---
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
        // --- END JavaScript for Sidebar Toggle ---
    </script>
</body>
</html>
<?php
// Close the database connection once all operations are complete
if (isset($conn)) {
    // Check if the connection object is valid before attempting to close
    if ($conn->close() === false) {
        error_log("Failed to close database connection at end of script.");
    }
}
?>