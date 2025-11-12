<?php
session_start();
include '../includes/connection.php'; // Ensure this path is correct for your setup

// --- 1. Robust Connection Check ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? "Connection object not set"));
    header("location: database_error.php"); 
    exit();
}

// --- 2. Authentication & Authorization Check ---
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: admin-login.php");
    exit();
}

$admin_user_id = $_SESSION['user_id'];
$display_name = 'Admin'; 
$is_admin = false; 

// Fetch admin's name AND user type for security check
$stmt_admin_name = $conn->prepare("SELECT name, user_type FROM users WHERE user_id = ?");
if ($stmt_admin_name) {
    $stmt_admin_name->bind_param("i", $admin_user_id);
    $stmt_admin_name->execute();
    $stmt_admin_name->bind_result($db_name, $db_user_type);
    $stmt_admin_name->fetch();
    $stmt_admin_name->close();

    if ($db_name) {
        $display_name = htmlspecialchars($db_name);
    }
    
    if ($db_user_type === 'admin') {
        $is_admin = true;
    } 
} else {
    error_log("Failed to prepare statement for admin name/type: " . $conn->error);
}

// Enforce Admin Access
if (!$is_admin) {
    session_unset();
    session_destroy();
    header("location: admin-login.php");
    exit();
}


// --- 3. Handle PRG Session Messages & URL Parameters ---
$message = '';
$message_type = ''; 
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// --- 4. Get and Validate farmer_id from URL ---
$farmer_id = filter_var($_GET['farmer_id'] ?? null, FILTER_VALIDATE_INT);
$farmer_data = null;

if ($farmer_id === false || $farmer_id <= 0) {
    // Redirect if farmer_id is missing or invalid
    $_SESSION['message'] = "Invalid or missing Farmer ID.";
    $_SESSION['message_type'] = 'danger';
    header("Location: admin-register_farmer.php");
    exit();
}


// --- 5. Fetch Farmer Details and QR Code URL ---
$stmt_farmer = $conn->prepare("
    SELECT 
        first_name, 
        middle_name,
        last_name, 
        rsbsa_id, 
        qr_code_image 
    FROM 
        farmers 
    WHERE 
        farmer_id = ?
");

if ($stmt_farmer) {
    $stmt_farmer->bind_param("i", $farmer_id);
    $stmt_farmer->execute();
    $result = $stmt_farmer->get_result();
    
    if ($result->num_rows === 1) {
        $farmer_data = $result->fetch_assoc();
    } else {
        // Farmer not found
        $_SESSION['message'] = "Farmer details not found for ID: " . htmlspecialchars($farmer_id);
        $_SESSION['message_type'] = 'danger';
        $stmt_farmer->close();
        $conn->close();
        header("Location: admin-register_farmer.php");
        exit();
    }
    $stmt_farmer->close();
} else {
    error_log("Failed to prepare statement for fetching farmer: " . $conn->error);
    $_SESSION['message'] = "Database error while fetching farmer data.";
    $_SESSION['message_type'] = 'danger';
    $conn->close();
    header("Location: admin-register_farmer.php");
    exit();
}

// Close the connection
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
            max-width: 800px;
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
        
        /* Styles for printing */
        @media print {
            body * {
                visibility: hidden;
            }
            #qr-print-area, #qr-print-area * {
                visibility: visible;
            }
            #qr-print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 15px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <nav class="sidebar no-print">
        <a href="admin-dashboard.php" class="header-brand">
            <img src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Province of Antique" />
            <div>Province of Antique</div>
        </a>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="admin-dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-add_user.php" class="nav-link">
                    <i class="fas fa-user-plus"></i> Add User
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-view_farmers.php" class="nav-link">
                    <i class="fas fa-users"></i> View Farmers
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-register_farmer.php" class="nav-link active">
                    <i class="fas fa-clipboard-list"></i> Register Farmer Details
                </a>
            </li>
        </ul>
    </nav>

    <!-- Header -->
    <div class="card-header card-header-custom d-flex justify-content-end align-items-center no-print">
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <button class="logout-btn" onclick="location.href='admin-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h1 class="page-title no-print">Farmer QR Code Display</h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show no-print" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($farmer_data): ?>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-success mb-4 no-print">Registration Successful!</h5>
                        
                        <!-- Print Area -->
                        <div id="qr-print-area" class="text-center p-4">
                            <h3 class="mb-3 text-dark">
                                <!-- Concatenates first and last name to match the screenshot style -->
                                Farmer Card (<?php echo htmlspecialchars($farmer_data['first_name'] . ' ' . $farmer_data['last_name']); ?>)
                            </h3>
                            <p class="lead">RSBSA ID: <strong><?php echo htmlspecialchars($farmer_data['rsbsa_id']); ?></strong></p>

                            <?php if (!empty($farmer_data['qr_code_image'])): ?>
                                <img src="<?php echo htmlspecialchars($farmer_data['qr_code_image']); ?>" alt="Farmer QR Code" class="img-fluid border border-3 p-2 my-3" style="max-width: 250px;">
                                <p class="text-muted small">Scan this code to view the farmer's complete details.</p>
                            <?php else: ?>
                                <div class="alert alert-warning">QR Code image is missing. Please contact support.</div>
                            <?php endif; ?>
                            
                            <hr class="my-4">
                            <small class="text-muted">Issued by Province of Antique DA</small>
                        </div>
                        <!-- End Print Area -->

                        <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4 no-print">
                            <button onclick="window.print()" class="btn btn-primary btn-lg">
                                <i class="fas fa-print me-2"></i> Print QR Code
                            </button>
                            <a href="admin-register_farmer.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-arrow-left me-2"></i> Register Another Farmer
                            </a>
                        </div>
                    </div> 
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    Error: Farmer data could not be loaded. Please ensure you came from the registration page.
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>