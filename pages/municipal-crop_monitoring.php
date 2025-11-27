<?php
session_start();

include '../includes/connection.php'; // Ensure this path is correct

// Redirect if user_id is not set or not an integer
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: municipal-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Mao'; // Default fallback

// --- IMPROVED NAME FETCHING ---
// Always try to fetch the name from the database for accuracy.
// This ensures that if the session name is outdated or not set, the DB name is used.
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

// =================================================================
// --- PAGINATION SETUP ---
// =================================================================

$cm_page = isset($_GET['cm_page']) && is_numeric($_GET['cm_page']) && $_GET['cm_page'] > 0 ? intval($_GET['cm_page']) : 1;
$cm_limit = 10; // 10 rows per page for the monitoring table
$cm_offset = ($cm_page - 1) * $cm_limit;

// 1. Count Total Rows
$count_sql = "SELECT COUNT(ps.id)
            FROM planting_status ps
            JOIN users u ON ps.user_id = u.user_id
            LEFT JOIN farmers f ON u.user_id = f.user_id";

$count_result = $conn->query($count_sql);
$total_cm_rows = 0;
if ($count_result) {
    $total_cm_rows = $count_result->fetch_row()[0];
}
$total_cm_pages = ceil($total_cm_rows / $cm_limit);

// Ensure current page is not out of bounds
if ($cm_page > $total_cm_pages && $total_cm_pages > 0) {
    $cm_page = $total_cm_pages;
    $cm_offset = ($cm_page - 1) * $cm_limit;
} else if ($total_cm_pages == 0) {
     $cm_page = 1;
     $cm_offset = 0;
}


// Fetch crop monitoring data for the current page, including photo_path
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
            ps.update_date DESC
        LIMIT ? OFFSET ?"; // ADDED LIMIT/OFFSET for pagination

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $cm_limit, $cm_offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $crop_monitoring_data[] = $row;
        }
    }
    $stmt->close();
} else {
    error_log("Failed to prepare paginated crop monitoring statement: " . $conn->error);
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
    <!-- Google Fonts (UPDATED FOR CONSISTENCY) -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Custom Styles (UPDATED FOR CONSISTENCY) -->
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

        /* --- Sidebar Styles (CONSISTENT DESIGN) --- */
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

        .sidebar-menu-label {
            color: rgba(255, 255, 255, 0.7);
            padding: 0 1rem 0.5rem 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sidebar.collapsed {
            left: -250px;
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

        /* --- Fixed Top Header (CONSISTENT DESIGN) --- */
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

        /* --- Main Content Area (CONSISTENT DESIGN) --- */
        main {
            margin-left: 250px;
            padding: 72px 2rem 2rem 2rem;
            background: #f8f9fa;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        main.collapsed {
            margin-left: 0;
        }
        
        .page-title { /* CONSISTENT TYPOGRAPHY */
            font-family: "Be Vietnam Pro", sans-serif;
            color: #0f5132;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .dashboard-description { /* CONSISTENT TYPOGRAPHY */
            font-size: 0.875rem; /* 14px */
        }
        
        .btn-theme { /* CONSISTENT BUTTONS */
            background-color: #19860f;
            color: #fff;
            border-color: #19860f;
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 15px;
            padding: 10px 20px;
            border-radius: 4px;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-theme:hover {
            background-color: #146c0b;
            border-color: #146c0b;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            border: 1px solid #ddd;
        }

        /* Table and form element consistency */
        .filter-select {
            font-family: "Poppins", sans-serif;
        }
        
        .table th, .table td {
            font-family: "Poppins", sans-serif;
            font-size: 0.9375rem; /* ~15px */
        }

        /* Custom status badge classes (UPDATED FOR CONSISTENCY) */
        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.4rem;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .status-planted {
            background-color: #198754 !important; /* Success */
            color: #fff !important;
        }
        .status-harvested {
            background-color: #0dcaf0 !important; /* Info (light blue) */
            color: #000 !important; /* Dark text on light blue */
        }
        .status-pending {
            background-color: #ffc107 !important; /* Warning */
            color: #664d03 !important;
        }
        .status-no-update {
            background-color: #dc3545 !important; /* Danger */
            color: #fff !important;
        }

        /* Consistent Pagination Styling */
        .pagination .page-item.active .page-link {
            background-color: #19860f !important;
            border-color: #19860f !important;
            color: #fff !important;
        }
        .pagination .page-item .page-link {
            color: #19860f;
        }
        .pagination .page-item .page-link:hover {
            background-color: #e6f6e4;
            color: #146c0b;
        }
        
        #sidebarToggleBtn {
            color: #0f5132;
        }

        #sidebarToggleBtn:hover {
            color: #146c0b;
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
    <!-- Sidebar (UPDATED FOR CONSISTENCY) -->
    <nav class="sidebar">
        <!-- Logo and Text -->
        <a href="municipal-dashboard.php" class="header-brand">
            <img src="../photos/logo.png" alt="Agriconnect Logo" />
            <div>Agriconnect</div>
        </a>
        
        <!-- Menu Label -->
        <div class="sidebar-menu-label">Main Menu</div>
        
        <ul class="nav flex-column">
            <li class="nav-item"><a href="municipal-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="municipal-subsidy_management.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i> Subsidy Management</a></li>
            <li class="nav-item"><a href="municipal-qrcode_management.php" class="nav-link"><i class="fas fa-qrcode"></i> QR Code Management</a></li>
            <li class="nav-item"><a href="municipal-crop_monitoring.php" class="nav-link active"><i class="fas fa-seedling"></i> Crop Monitoring</a></li>
            <li class="nav-item"><a href="municipal-reports_analytics.php" class="nav-link"><i class="fas fa-chart-line"></i> Reports & Analytics</a></li>
            <li class="nav-item"><a href="municipal-farmer_profiles.php" class="nav-link"><i class="fas fa-users"></i> Farmer Profiles</a></li>
            <li class="nav-item"><a href="municipal-announcements.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
        </ul>
        <!-- Logout Section -->
        <div class="sidebar-logout">
             <a href="municipal-logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Header (UPDATED FOR CONSISTENCY) -->
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
            <!-- Title and Description (UPDATED FOR CONSISTENCY) -->
            <h1 class="page-title">Crop Monitoring</h1>
            <p class="text-muted mb-4 dashboard-description">Monitor planting updates and crop growth submitted by farmers (Page <?php echo $cm_page; ?> of <?php echo $total_cm_pages; ?>).</p>

            <!-- Filter Options -->
            <div class="row mb-4 align-items-end">
                <div class="col-md-6">
                    <label for="filterType" class="form-label dashboard-description">Filter by</label>
                    <select class="form-select filter-select" id="filterType" onchange="filterTable()">
                        <option value="all">All</option>
                        <option value="address">Address (Current Page)</option>
                        <?php foreach ($unique_addresses as $address): ?>
                            <option value="Address: <?php echo htmlspecialchars($address); ?>">Address: <?php echo htmlspecialchars($address); ?></option>
                        <?php endforeach; ?>
                        <option value="farmer">Farmer (Current Page)</option>
                        <option value="notUpdated">No Recent Update (Current Page)</option>
                        <option value="status-planted">Status: Planted (Current Page)</option>
                        <option value="status-pending">Status: Pending (Current Page)</option>
                        <option value="status-harvested">Status: Harvested (Current Page)</option>
                    </select>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <button class="btn btn-theme btn-danger" onclick="sendReminders()">
                        <i class="fas fa-bell me-2"></i> Send Reminders to Inactive Farmers
                    </button>
                </div>
            </div>

            <!-- Monitoring Table -->
            <div class="table-responsive card p-3">
                <table class="table table-bordered table-hover bg-white" id="cropMonitoringTable">
                    <thead class="table-light">
                        <tr>
                            <!-- Removed <th>#</th> -->
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
                                <td colspan="6" class="text-center">No crop monitoring data found on this page.</td>
                            </tr>
                        <?php else: ?>
                            <?php $row_number = 1; // Kept the variable in case it's needed elsewhere, but not used in the display ?>
                            <?php foreach ($crop_monitoring_data as $data): ?>
                                <?php
                                    $status_class = '';
                                    // Use the specific class names defined in the new CSS
                                    switch (strtolower($data['status'])) {
                                        case 'planted':
                                        case 'seedling':
                                        case 'growing':
                                        case 'flowering':
                                        case 'harvesting':
                                            $status_class = 'status-planted';
                                            break;
                                        case 'pending':
                                            $status_class = 'status-pending';
                                            break;
                                        case 'harvested':
                                            $status_class = 'status-harvested';
                                            break;
                                        default:
                                            $status_class = 'bg-secondary text-white'; // Default fallback using general BS classes
                                            break;
                                    }
                                    
                                    $last_update_date = new DateTime($data['update_date']);
                                    $current_date = new DateTime();
                                    $interval = $current_date->diff($last_update_date);
                                    $days_since_update = $interval->days;

                                    if ($days_since_update > 30 && strtolower($data['status']) !== 'harvested') {
                                        $status_class = 'status-no-update';
                                        $display_status = 'No Update (' . $days_since_update . ' days)';
                                    } else {
                                        $display_status = $data['status'];
                                    }

                                    // Check if photo_path exists and is not empty
                                    $photo_available = !empty($data['photo_path']);
                                ?>
                                <tr>
                                    <!-- Removed <td><?php echo $row_number++; ?></td> -->
                                    <td><?php echo htmlspecialchars($data['farmer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($data['farmer_address']); ?></td>
                                    <td><?php echo htmlspecialchars($data['crop_identifier']); ?></td>
                                    <td><span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($display_status); ?></span></td>
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
            
            <!-- Pagination controls for Crop Monitoring Table -->
            <?php if ($total_cm_pages > 1): ?>
                <nav aria-label="Crop Monitoring Page navigation" class="d-flex justify-content-center mt-3">
                    <ul class="pagination">
                        <li class="page-item <?php echo ($cm_page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?cm_page=<?php echo max(1, $cm_page - 1); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php
                        // Logic to display a limited number of pages (e.g., 5)
                        $start_page = max(1, $cm_page - 2);
                        $end_page = min($total_cm_pages, $start_page + 4);
                        if ($end_page - $start_page < 4) {
                            $start_page = max(1, $end_page - 4);
                        }
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li class="page-item <?php echo ($cm_page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?cm_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($cm_page >= $total_cm_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?cm_page=<?php echo min($total_cm_pages, $cm_page + 1); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
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
        // --- Sidebar Toggle JavaScript (CONSISTENT DESIGN) ---
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
        
        const isCollapsed = localStorage.getItem('sidebarCollapsed');
        if (isCollapsed === 'true') {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('collapsed');
            header.classList.add('collapsed');
        } 
        
        toggleBtn.addEventListener('click', function() {
            if (sidebar.classList.contains('collapsed')) {
                openSidebar();
            } else {
                collapseSidebar();
            }
        });
        // --- End Sidebar Toggle JavaScript ---

        function filterTable() {
            // NOTE: Client-side filtering now only affects the current page's data due to server-side pagination.
            const filter = document.getElementById("filterType").value;
            const rows = document.querySelectorAll("#cropMonitoringTable tbody tr");

            rows.forEach((row) => {
                if (row.children.length < 6) return; // Adjusted from 7 to 6 columns

                // Adjusted indices after removing the first column ('#')
                const farmerName = row.children[0].textContent.toLowerCase(); // Index 1 -> 0
                const address = row.children[1].textContent.toLowerCase();    // Index 2 -> 1
                const statusElement = row.children[3].querySelector('.status-badge'); // Index 4 -> 3
                const statusText = statusElement ? statusElement.textContent.toLowerCase() : '';
                const lastUpdate = row.children[4].textContent;              // Index 5 -> 4
                const daysSinceUpdate = getDaysSince(lastUpdate);

                let show = true;

                if (filter.startsWith("Address: ")) { 
                    const specificAddress = filter.replace("Address: ", "").toLowerCase();
                    show = address.includes(specificAddress);
                } else if (filter === "farmer") {
                    // This is a placeholder filter, a search box would be better.
                    // For now, it shows all as "juan" is too specific.
                    show = true; 
                } else if (filter === "notUpdated") {
                    // Match the logic used in the PHP to tag "No Update"
                    show = daysSinceUpdate > 30 && !statusText.includes("harvested"); 
                } else if (filter.startsWith("status-")) {
                    const statusFilter = filter.replace("status-", "");
                    // The statusText from the badge might contain 'No Update (X days)', 
                    // so we only check the specific status filter part
                    if (statusFilter === 'planted') {
                         // Check for any non-terminal/non-no-update status
                         show = (statusText.includes('planted') || statusText.includes('seedling') || statusText.includes('growing') || statusText.includes('flowering') || statusText.includes('harvesting')) && !statusText.includes('no update');
                    } else if (statusFilter === 'pending') {
                         show = statusText.includes('pending') && !statusText.includes('no update');
                    } else if (statusFilter === 'harvested') {
                         show = statusText.includes('harvested');
                    }
                } else if (filter === "all" || filter === "address") {
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
                // Construct the full path. Assuming the path is relative to the current file, 
                // e.g., if photoPath is 'uploads/planting_photos/...'
                modalImage.src = photoPath; // Set the image source
            });
        });
    </script>
</body>

</html>