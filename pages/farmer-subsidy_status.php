<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("location: farmers-login.php");
    exit();
}

$display_name = $_SESSION['name'] ?? 'Farmer';
$user_id = $_SESSION['user_id']; // Get the logged-in user's ID

$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "cap101";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    // Consider a user-friendly error page here
    die("Error connecting to the database.");
}

// Fetch user's name
$stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($fetched_db_name);
$stmt->fetch();
if ($fetched_db_name) {
    $display_name = $fetched_db_name;
}
$stmt->close();

// Initialize variables
$approved_qr_code = null;
$farmer_id_display = null;
$application_id_for_display = null; // Store application_id for dynamic QR code data if needed

// Fetch approved application data, including qr_code_data
// Added application_id to the select for potential dynamic QR code data generation if qr_code_data is empty
$stmt_qr = $conn->prepare("SELECT application_id, qr_code_data FROM assistance_applications WHERE farmer_user_id = ? AND status = 'Approved' LIMIT 1");
$stmt_qr->bind_param("i", $user_id);
$stmt_qr->execute();
$stmt_qr->bind_result($fetched_application_id, $fetched_qr_code_data);
$stmt_qr->fetch();

if ($fetched_application_id) { // Check if an application was found
    $application_id_for_display = $fetched_application_id;
    $farmer_id_display = "FRM-" . str_pad($fetched_application_id, 9, '0', STR_PAD_LEFT);

    // --- CRITICAL FIX/IMPROVEMENT HERE ---
    // If qr_code_data is explicitly NULL or empty in the database,
    // you need a fallback or a way to generate it here.
    // Ideally, qr_code_data should be generated and stored when the application is approved.
    if (!empty($fetched_qr_code_data)) {
        $approved_qr_code = $fetched_qr_code_data;
    } else {
        // Fallback: If qr_code_data is missing, generate a simple one based on application ID.
        // THIS LINE HAS BEEN MODIFIED to remove the user_id from the QR data.
        $approved_qr_code = "app_id:" . $fetched_application_id;
        // Optionally, update the database here if you want to store this generated QR data
        // $update_stmt = $conn->prepare("UPDATE assistance_applications SET qr_code_data = ? WHERE application_id = ?");
        // $update_stmt->bind_param("si", $approved_qr_code, $fetched_application_id);
        // $update_stmt->execute();
        // $update_stmt->close();
    }
}
$stmt_qr->close();

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Account - Subsidy Status</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Custom Styles -->
    <style>
        /* Your CSS styles remain the same */
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

        .card-header-custom .header-brand span {
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
            transition: all 0.2s ease;
        }

        .btn-theme:hover {
            background-color: #146c0b;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
            display: flex;
            align-items: center;
        }

        .page-title i {
            margin-right: 10px;
        }

        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .card-title {
            color: #19860f;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
        }

        .card-title i {
            margin-right: 8px;
        }

        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.4rem;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }

        .status-badge i {
            margin-right: 5px;
        }

        .status-pending {
            background-color: #ffc107;
            color: #856404;
        }

        .status-approved {
            background-color: #28a745;
            color: #fff;
        }

        .status-claimed {
            background-color: #0d6efd;
            color: #fff;
        }

        .status-eligible {
            background-color: #28a745;
            color: #fff;
        }

        .status-rejected {
            background-color: #dc3545;
            color: #fff;
        }

        .qr-code {
            text-align: center;
            margin-top: 1rem;
        }

        .qr-code img {
            width: 180px;
            height: 180px;
            border: 5px solid #19860f;
            border-radius: 8px;
            padding: 8px;
            background: #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .table thead th {
            background-color: #e9f5ee;
            color: #19860f;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.75rem 1rem;
        }

        .table tbody tr td {
            font-size: 0.95rem;
            padding: 0.75rem 1rem;
        }

        .table tbody tr:nth-child(even) {
            background-color: #f3fcf5;
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
                <a href="farmer-subsidy_status.php" class="nav-link active">
                    <i class="fas fa-hand-holding-usd"></i> Subsidy Status
                </a>
            </li>
            <li class="nav-item">
                <a href="farmer-announcement.php" class="nav-link">
                    <i class="fas fa-bullhorn"></i> Announcements
                </a>
            </li>
            <li class="nav-item">
                <a href="farmer-apply_for_assistance.php" class="nav-link">
                    <i class="fas fa-file-invoice"></i> Apply for Assistance
                </a>
            </li>
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
    </nav>

    <!-- Header (Top Nav) -->
    <div class="card-header card-header-custom d-flex justify-content-end align-items-center">
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <button class="logout-btn" onclick="location.href='farmers-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h2 class="page-title"></i> Subsidy Status</h2>
            <p class="text-muted mb-4">
                Approved assistance details and your unique QR code for claiming.
            </p>

            <div class="row">

                <!-- QR Code for Claiming (Conditional Display) -->
                <div class="col-12">
                    <div class="card p-4 text-center">
                        <?php if ($approved_qr_code): ?>
                            <h5 class="card-title justify-content-center"><i class="fas fa-qrcode"></i> Your Claim QR Code</h5>
                            <p class="card-text text-muted small">Present this unique QR code at authorized claiming centers for your pending assistance.</p>
                            <div class="qr-code mb-3">
                                <!-- Ensure the QR code data is properly URL-encoded -->
                                <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php echo urlencode($approved_qr_code); ?>&size=200x200" alt="Claim QR Code">
                            </div>
                            <button class="btn btn-theme col-lg-4 col-md-6 mx-auto" onclick="downloadQRCode('<?php echo urlencode($approved_qr_code); ?>', '<?php echo htmlspecialchars($farmer_id_display); ?>')"><i class="fas fa-download me-2"></i> Download QR Code</button>
                            <p class="text-muted small mt-3">Always keep your QR code secure. It is unique to your approved claims.</p>
                        <?php else: ?>
                            <h5 class="card-title justify-content-center"><i class="fas fa-info-circle"></i> No Approved Claims Yet</h5>
                            <p class="card-text text-muted">
                                We're sorry, there are no approved assistance applications with a QR code generated for your account at this time.
                                Please check back later or contact your local agricultural office for more information.
                            </p>
                            <a href="farmer-apply_for_assistance.php" class="btn btn-theme col-lg-4 col-md-6 mx-auto mt-3">
                                <i class="fas fa-file-invoice me-2"></i> Apply for New Assistance
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function downloadQRCode(qrData, farmerId) {
            const qrCodeUrl = `https://api.qrserver.com/v1/create-qr-code/?data=${qrData}&size=400x400`; // Use a larger size for download
            const link = document.createElement('a');
            link.href = qrCodeUrl;
            link.download = `Farmer_QRCode_${farmerId || 'Claim'}.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>

</html>