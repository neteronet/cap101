<?php
session_start();
include '../includes/connection.php'; // Path to your database connection

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: admin-login.php");
    exit();
}

$farmer_id = $_GET['farmer_id'] ?? null;
$message = htmlspecialchars($_GET['msg'] ?? '');
$message_type = htmlspecialchars($_GET['type'] ?? '');
$farmer_data = null;
$qr_code_url = '';
$display_name = 'Admin'; // Default fallback

// Fetch admin's name (same as in admin-register_farmer.php)
$admin_user_id = $_SESSION['user_id'];
$stmt_admin_name = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
if ($stmt_admin_name) {
    $stmt_admin_name->bind_param("i", $admin_user_id);
    $stmt_admin_name->execute();
    $stmt_admin_name->bind_result($db_name);
    $stmt_admin_name->fetch();
    if ($db_name) {
        $display_name = htmlspecialchars($db_name);
    }
    $stmt_admin_name->close();
}


if ($farmer_id && is_numeric($farmer_id)) {
    // Fetch farmer data for QR code content
    $stmt_farmer = $conn->prepare("
        SELECT 
            first_name, 
            middle_name, 
            last_name, 
            rsbsa_id
        FROM 
            farmers
        WHERE 
            farmer_id = ?
    ");

    if ($stmt_farmer) {
        $stmt_farmer->bind_param("i", $farmer_id);
        $stmt_farmer->execute();
        $result = $stmt_farmer->get_result();
        $farmer_data = $result->fetch_assoc();
        $stmt_farmer->close();

        if ($farmer_data) {
            $full_name = trim($farmer_data['first_name'] . ' ' . $farmer_data['middle_name'] . ' ' . $farmer_data['last_name']);
            
            // Data to be encoded in the QR code (Can be a URL or simple data)
            // Example: Link to a view page (recommended)
            // $qr_content = 'https://yourdomain.com/view_farmer_details.php?id=' . $farmer_id;
            
            // Example: Simple identification data (used below)
            $qr_content = "FARMER_ID: {$farmer_id} | RSBSA_ID: {$farmer_data['rsbsa_id']} | Name: {$full_name}";
            
            // --- START MODIFIED CODE ---
            // Generate QR Code URL using api.qrserver.com
            $qr_size = '300x300';
            // The API uses 'data' for the content and 'size' for the dimensions.
            // Added error correction (ecc=L) and format (format=png) for clarity.
            $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size={$qr_size}&data=" . urlencode($qr_content) . "&ecc=L&format=png";
            // --- END MODIFIED CODE ---
            
        } else {
            $message = "Farmer ID not found.";
            $message_type = 'danger';
        }
    } else {
        error_log("Failed to prepare statement for fetching farmer data: " . $conn->error);
        $message = "Database error fetching farmer data.";
        $message_type = 'danger';
    }
} else {
    $message = "Invalid or missing Farmer ID.";
    $message_type = 'danger';
}

if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin - Farmer QR Code</title>
    <!-- Include your existing styles/Bootstrap links here -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    
    <!-- Custom Styles (copy/paste your custom CSS for sidebar/main layout) -->
    <style>
        /* ... (Copy all your custom CSS from admin-register_farmer.php here) ... */
        body { font-family: "Poppins", sans-serif; background: #f8f9fa; font-size: 16px; line-height: 1.6; color: #333; margin: 0; }
        .sidebar { position: fixed; top: 0; left: 0; width: 250px; height: 100vh; background: #19860f; padding: 1rem 0; overflow-y: auto; font-size: 14px; z-index: 1050; border-right: 1px solid #ddd; }
        .sidebar .nav-link { color: #fff; padding: 0.6rem 1rem; width: 100%; box-sizing: border-box; border-radius: 0; display: flex; align-items: center; text-decoration: none; }
        .sidebar .nav-link i { margin-right: 8px; font-size: 1rem; }
        .sidebar .nav-link.active { background-color: #fff; color: #19860f; font-weight: 600; }
        .sidebar .nav-link:hover:not(.active) { background-color: #146c0b; color: #fff; }
        .sidebar .header-brand { display: flex; flex-direction: column; align-items: center; text-decoration: none; margin-bottom: 1rem; }
        .sidebar .header-brand img { width: 100%; max-width: 120px; height: auto; background: #19860f; padding: 5px; border-radius: 4px; }
        .sidebar .header-brand div { font-size: 14px; font-weight: 600; color: #fff; text-align: center; margin-top: 6px; }
        .card-header-custom { position: fixed; top: 0; left: 250px; right: 0; height: 56px; background-color: #fff; color: #19860f; padding: 0 1.25rem; font-weight: 500; font-size: 1rem; display: flex; align-items: center; justify-content: space-between; z-index: 1060; border-bottom: 1px solid #ddd; }
        .header-brand span { font-size: 1rem; font-weight: 600; color: #19860f; }
        .logout-btn { background: #ff4b2b; color: #fff; border: none; padding: 6px 14px; font-size: 14px; border-radius: 20px; transition: background 0.2s ease; cursor: pointer; }
        .logout-btn:hover { background: #e04325; }
        .btn-theme { background-color: #19860f; color: #fff; font-size: 15px; padding: 10px 20px; border-radius: 4px; }
        .btn-theme:hover { background-color: #146c0b; }
        main { margin-left: 250px; padding: 1rem 2rem 2rem 2rem; padding-top: 72px; background: #f8f9fa; min-height: 100vh; }
        .container { max-width: 900px; }
        .page-title { font-size: 1.8rem; font-weight: 600; color: #19860f; margin-bottom: 1rem; }
        .card { border-radius: 0.5rem; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05); margin-bottom: 1rem; }
        .card-title { color: #19860f; font-size: 1.25rem; margin-bottom: 0.75rem; }
    </style>
</head>

<body>
    <!-- Sidebar (Same as admin-register_farmer.php) -->
    <nav class="sidebar">
        <!-- ... (Navigation links) ... -->
        <a href="admin-dashboard.php" class="header-brand">
            <img src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Province of Antique" />
            <div>Province of Antique</div>
        </a>

        <ul class="nav flex-column">
            <li class="nav-item"><a href="admin-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="admin-add_user.php" class="nav-link"><i class="fas fa-user-plus"></i> Add User</a></li>
            <li class="nav-item"><a href="admin-view_farmers.php" class="nav-link"><i class="fas fa-users"></i> View Farmers</a></li>
            <li class="nav-item"><a href="admin-register_farmer.php" class="nav-link active"><i class="fas fa-clipboard-list"></i> Register Farmer Details</a></li>
        </ul>
    </nav>

    <!-- Header (Same as admin-register_farmer.php) -->
    <div class="card-header card-header-custom d-flex justify-content-end align-items-center">
        <span class="me-3">Hi, <strong><?php echo $display_name; ?></strong></span>
        <button class="logout-btn" onclick="location.href='admin-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h1 class="page-title">Farmer QR Code Generation</h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($farmer_data): ?>
                <div class="card text-center p-4">
                    <h5 class="card-title mb-4">QR Code for <?php echo htmlspecialchars($farmer_data['first_name'] . ' ' . $farmer_data['last_name']); ?></h5>
                    
                    <div class="mb-3">
                        <p class="mb-1"><strong>Farmer ID:</strong> <?php echo htmlspecialchars($farmer_id); ?></p>
                        <p><strong>RSBSA ID:</strong> <?php echo htmlspecialchars($farmer_data['rsbsa_id']); ?></p>
                    </div>

                    <div class="d-flex justify-content-center mb-4">
                        <img src="<?php echo $qr_code_url; ?>" alt="QR Code for Farmer ID <?php echo htmlspecialchars($farmer_id); ?>" style="border: 2px solid #ccc; padding: 10px;">
                    </div>
                    
                    <button class="btn btn-theme me-2" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print QR Code
                    </button>
                    <a href="admin-register_farmer.php" class="btn btn-secondary mt-2">
                        <i class="fas fa-plus me-1"></i> Register Another Farmer
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Could not find farmer details to generate QR code.</div>
                <a href="admin-register_farmer.php" class="btn btn-theme">
                    <i class="fas fa-arrow-left me-1"></i> Back to Registration
                </a>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>