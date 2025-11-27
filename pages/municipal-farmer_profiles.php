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
$stmt_name = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
if ($stmt_name) {
    $stmt_name->bind_param("i", $user_id);
    $stmt_name->execute();
    $stmt_name->bind_result($db_name);
    $stmt_name->fetch();
    if ($db_name) {
        $display_name = htmlspecialchars($db_name);
    }
    $stmt_name->close();
}

// --- PAGINATION CONFIGURATION ---
$results_per_page = 10; // Limit to 10 farmers per page (so the 11th creates a new page)

// Determine which page number visitor is currently on
if (!isset($_GET['page'])) {
    $page = 1;
} else {
    $page = (int)$_GET['page'];
}

// Determine the SQL LIMIT starting number for the results on the displaying page
$page_first_result = ($page - 1) * $results_per_page;

// --- FETCH TOTAL RECORDS ---
$sql_count = "SELECT COUNT(*) AS total FROM farmers";
$result_count = $conn->query($sql_count);
$row_count = $result_count->fetch_assoc();
$total_records = $row_count['total'];

// Determine number of total pages available
$number_of_pages = ceil($total_records / $results_per_page);

// --- FETCH FARMER DATA FOR CURRENT PAGE ---
$farmers = [];
$sql = "SELECT farmer_id, first_name, middle_name, last_name, address, contact_number, land_details, age, gender, civil_status, crop
        FROM farmers
        ORDER BY last_name ASC
        LIMIT " . $page_first_result . ',' . $results_per_page;

$result = $conn->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $farmers[] = $row;
        }
    }
    $result->free();
} else {
    error_log("Error fetching farmer profiles: " . $conn->error);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Account - Farmer Profiles</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts (CONSISTENT FONT STYLING) -->
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Custom Styles (COPIED FROM farmer-dashboard.php FOR CONSISTENCY) -->
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

        /* --- Main Content Area --- */
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

        /* General Consistency */
        .text-muted {
            color: #6c757d !important;
        }

        h1, h2, h3, h4, h5, h6, .card-title, .modal-title, .page-title {
            font-family: "Be Vietnam Pro", sans-serif;
            color: #0f5132;
        }
        
        /* Consistent Page Title Style */
        .page-title {
            font-size: 1.5rem; 
            font-weight: 600; 
            margin-bottom: 0.5rem;
        }
        
        /* Consistent Description Paragraph Style */
        .dashboard-description {
            font-size: 0.875rem; 
        }

        /* Consistent Card Title Size */
        .card-title {
            font-size: 1.25rem; 
            font-weight: 600; 
        }
        
        /* Consistent Standard Card Text Size */
        .card-text, 
        .card-body p:not(.card-title), 
        .list-unstyled li {
            font-size: 0.9375rem; 
        }
        
        /* Button Theme Consistency */
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
        
        /* Card Styles */
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            border: 1px solid #ddd;
        }

        /* Table Styles */
        .table thead th {
            background-color: #f2f2f2;
            color: #555;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
            font-family: "Be Vietnam Pro", sans-serif; /* Use Be Vietnam Pro for table headers */
        }
        
        .table tbody {
            font-family: "Poppins", sans-serif; /* Use Poppins for table body */
        }

        .table tbody tr:hover {
            background-color: #f0f8f0;
        }

        /* Pagination Styles */
        .pagination .page-item .page-link {
            color: #19860f;
            border: 1px solid #dee2e6;
            margin: 0 2px;
            border-radius: 4px;
            font-family: "Be Vietnam Pro", sans-serif;
        }

        .pagination .page-item.active .page-link {
            background-color: #19860f;
            border-color: #19860f;
            color: #fff;
        }

        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #fff;
            border-color: #dee2e6;
        }

        .pagination .page-link:hover {
            background-color: #e9ecef;
            color: #146c0b;
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
    <!-- Sidebar (CONSISTENT DESIGN) -->
    <nav class="sidebar">
        <!-- Logo and Text (Consistent with farmer-dashboard.php) -->
        <a href="municipal-dashboard.php" class="header-brand">
            <img src="../photos/logo.png" alt="Department of Agriculture Logo" />
            <div>Agriconnect</div>
        </a>

        <!-- Menu Label (Consistent) -->
        <div class="sidebar-menu-label">Main Menu</div>

        <ul class="nav flex-column">
            <!-- Ensure correct paths and active state -->
            <li class="nav-item"><a href="municipal-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="municipal-subsidy_management.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i> Subsidy Management</a></li>
            <li class="nav-item"><a href="municipal-qrcode_management.php" class="nav-link"><i class="fas fa-qrcode"></i> QR Code Management</a></li>
            <li class="nav-item"><a href="municipal-crop_monitoring.php" class="nav-link"><i class="fas fa-seedling"></i> Crop Monitoring</a></li>
            <li class="nav-item"><a href="municipal-reports_analytics.php" class="nav-link"><i class="fas fa-chart-line"></i> Reports & Analytics</a></li>
            <li class="nav-item"><a href="municipal-farmer_profiles.php" class="nav-link active"><i class="fas fa-users"></i> Farmer Profiles</a></li>
            <li class="nav-item"><a href="municipal-announcements.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
        </ul>
        
        <!-- Logout Section (Consistent) -->
        <div class="sidebar-logout">
            <a href="municipal-logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Header (CONSISTENT DESIGN with Toggle Button) -->
    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
        <!-- Sidebar Toggle Button (Consistent) -->
        <button id="sidebarToggleBtn" class="btn btn-link p-0 text-dark" title="Toggle Sidebar" style="font-size: 1.5rem;">
            <i class="fas fa-bars"></i>
        </button>
        <!-- Greeting -->
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <!-- Removed explicit logout button from header for consistency; rely on sidebar logout -->
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h1 class="page-title">Farmer Profiles</h1>
            <p class="text-muted mb-4 dashboard-description">View and manage registered farmer records.</p>

            <!-- Search Bar -->
            <div class="input-group mb-3">
                <input type="text" class="form-control" placeholder="Search by name, address, or ID..." id="searchInput">
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
                                    <th>Full Name</th>
                                    <th>Address</th>
                                    <th>Contact</th>
                                    <th>Farm Size (ha)</th>
                                    <th>Crop Type</th>
                                </tr>
                            </thead>
                            <tbody id="farmerTableBody">
                                <?php if (empty($farmers)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No farmer profiles found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($farmers as $farmer): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($farmer['first_name'] . ' ' . (!empty($farmer['middle_name']) ? substr($farmer['middle_name'], 0, 1) . '. ' : '') . $farmer['last_name']); ?></td>
                                            <td>
                                                <?php
                                                    $address_parts = explode(',', $farmer['address']);
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
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION LINKS -->
                    <?php if ($number_of_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            
                            <!-- Previous Button -->
                            <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
                                <a class="page-link" href="<?php if($page > 1){ echo "?page=".($page - 1); } else { echo "#"; } ?>" aria-label="Previous">
                                    <span aria-hidden="true">&lt;</span>
                                </a>
                            </li>

                            <!-- Page Numbers -->
                            <?php for($i = 1; $i <= $number_of_pages; $i++): ?>
                                <li class="page-item <?php if($page == $i) { echo 'active'; } ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next Button -->
                            <li class="page-item <?php if($page >= $number_of_pages){ echo 'disabled'; } ?>">
                                <a class="page-link" href="<?php if($page < $number_of_pages){ echo "?page=".($page + 1); } else { echo "#"; } ?>" aria-label="Next">
                                    <span aria-hidden="true">&gt;</span>
                                </a>
                            </li>

                        </ul>
                    </nav>
                    <?php endif; ?>
                    <!-- END PAGINATION -->

                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript for Sidebar Toggle (COPIED FROM farmer-dashboard.php) -->
    <script>
    // JavaScript to toggle sidebar collapse and preserve state using localStorage
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
        // Apply collapsed state without saving back to localStorage
        sidebar.classList.add('collapsed');
        mainContent.classList.add('collapsed');
        header.classList.add('collapsed');
    } 

    // Toggle button functionality
    toggleBtn.addEventListener('click', function() {
        if (sidebar.classList.contains('collapsed')) {
            openSidebar();
        } else {
            collapseSidebar();
        }
    });

    // Existing farmer search function
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