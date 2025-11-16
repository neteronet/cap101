<?php
session_start();
include '../includes/connection.php';

// --- IMPROVEMENT 1: Robust Connection Check ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? "Connection object not set"));
    // Redirect to login on critical error
    header("location: farmers-login.php");
    exit();
}

// --- Check if the user is logged in ---
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: farmers-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Farmer'; // Default fallback
$farmer_id_display = "FRM-" . str_pad($user_id, 9, '0', STR_PAD_LEFT);
$is_farmer = false; // Flag for explicit farmer check

// --- IMPROVEMENT 2 & 3: Fetch Name AND User Type for Security Check ---
$stmt_name = $conn->prepare("SELECT name, user_type FROM users WHERE user_id = ?");
if ($stmt_name) {
    $stmt_name->bind_param("i", $user_id);
    $stmt_name->execute();
    $stmt_name->bind_result($db_name, $db_user_type);
    $stmt_name->fetch();
    $stmt_name->close();

    if ($db_name) {
        $display_name = htmlspecialchars($db_name);
    }

    // --- Explicit Farmer Authorization Check ---
    if ($db_user_type === 'farmer') {
        $is_farmer = true;
    } else {
        // If not a farmer, destroy session and redirect
        session_destroy();
        header("location: farmers-login.php");
        exit();
    }
} else {
    error_log("Failed to prepare statement for user name/type: " . $conn->error);
    // Treat preparation failure as a security risk/critical error
    session_destroy();
    header("location: farmers-login.php");
    exit();
}

// --- Application Status Variables & PRG Message Handling ---
$message = '';
$message_type = ''; 
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Initialize form field variables to null
$assistanceType = null;
$seedType = null;
$seedQuantity = null;
$engineType = null;
$remarks = null;

// --- CORE LOGIC: Check Latest Application Status for Current Year ---
$latest_app_id = null;
$latest_status = null;
$latest_qr_code = null;
$latest_approval_date = null;
$latest_claimed_status = null;

$stmt_latest = $conn->prepare("
    SELECT application_id, status, qr_code_data, DATE(approval_date), claimed
    FROM assistance_applications
    WHERE user_id = ?
      AND YEAR(application_date) = YEAR(CURDATE())
    ORDER BY application_date DESC, application_id DESC
    LIMIT 1
");

$application_exists_this_year = false;
$allow_new_application = true; // Assume true unless blocked by PENDING or APPROVED/UNCLAIMED
$is_approved_unclaimed = false; // Flag to trigger QR code display

if ($stmt_latest) {
    $stmt_latest->bind_param("i", $user_id);
    $stmt_latest->execute();
    $stmt_latest->bind_result($latest_app_id, $latest_status, $latest_qr_code, $latest_approval_date, $latest_claimed_status);

    if ($stmt_latest->fetch()) {
        $application_exists_this_year = true;
        
        // Determine if application is blocked (Pending or Approved/Unclaimed)
        if ($latest_status === 'Pending') {
            $allow_new_application = false; // Blocked by Pending
        } elseif ($latest_status === 'Approved' && $latest_claimed_status == 0) {
            $allow_new_application = false; // Blocked by Approved/Unclaimed
            $is_approved_unclaimed = true;
        }
        // If status is Rejected or Claimed (1), $allow_new_application remains true
    }
    $stmt_latest->close();
}

// --- QR Code Generation/Update Logic (Runs only if $is_approved_unclaimed is true) ---
$approved_qr_code = null;
if ($is_approved_unclaimed) {
    if (empty($latest_qr_code)) {
        // Generate QR data string
        $generated_qr_data = "app_id:" . $latest_app_id . "&user_id:" . $user_id . "&approved_on:" . $latest_approval_date;
        $approved_qr_code = $generated_qr_data;

        // Update database to save the generated code
        $update_stmt = $conn->prepare("UPDATE assistance_applications SET qr_code_data = ? WHERE application_id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("si", $generated_qr_data, $latest_app_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    } else {
        $approved_qr_code = $latest_qr_code;
    }
}


// --- Form Submission Handler (Only process POST if a new application is allowed) ---
if ($allow_new_application && $_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate common fields
    $assistanceType = filter_input(INPUT_POST, 'assistanceType', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $remarks = filter_input(INPUT_POST, 'remarks', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Validate main assistance type
    if (empty($assistanceType)) {
        $message = "Please select a Type of Assistance.";
        $message_type = "danger";
    } else {
        // Handle specific assistance types and their required fields
        switch ($assistanceType) {
            case 'Seeds':
                $seedType = filter_input(INPUT_POST, 'seedType', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $seedQuantity = filter_input(INPUT_POST, 'seedQuantity', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                
                if (empty($seedType) || empty($seedQuantity)) {
                    $message = "Please select Seed Type and Seed Quantity.";
                    $message_type = "danger";
                }
                break;
            case 'Fuel':
                $engineType = filter_input(INPUT_POST, 'engineType', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                
                if (empty($engineType)) {
                    $message = "Please select an Engine Type for fuel assistance.";
                    $message_type = "danger";
                }
                break;
            // Fertilizer and Cash Assistance require no other fields
            case 'Fertilizer':
            case 'Cash Assistance':
                break;
            default:
                $message = "Invalid assistance type selected.";
                $message_type = "danger";
                break;
        }

        // If no validation errors so far, proceed with database insertion
        if (empty($message)) {
            $status = 'Pending'; // Default status for new applications

            $insert_stmt = $conn->prepare("INSERT INTO assistance_applications (user_id, assistance_type, seed_type, seed_quantity, engine_type, remarks, status, application_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

            if ($insert_stmt === false) {
                error_log("Failed to prepare statement for insert: " . $conn->error);
                $message = "Database error: Could not prepare request. Please try again.";
                $message_type = "danger";
            } else {
                // Prepare null/empty string variables for non-selected options
                $db_seedType = ($assistanceType == 'Seeds') ? $seedType : '';
                $db_seedQuantity = ($assistanceType == 'Seeds') ? $seedQuantity : '';
                $db_engineType = ($assistanceType == 'Fuel') ? $engineType : '';
                
                // Bind parameters
                $insert_stmt->bind_param("issssss", 
                    $user_id, 
                    $assistanceType, 
                    $db_seedType,        
                    $db_seedQuantity,    
                    $db_engineType,      
                    $remarks, 
                    $status
                );

                // Execute the statement
                if ($insert_stmt->execute()) {
                    // --- Post/Redirect/Get (PRG) Pattern for Success ---
                    $_SESSION['message'] = "Your assistance request has been submitted successfully! Status: Pending.";
                    $_SESSION['message_type'] = "success";
                    $insert_stmt->close();
                    $conn->close(); // Close connection before redirect
                    header("Location: " . $_SERVER['PHP_SELF']); 
                    exit();
                } else {
                    error_log("Error submitting request for user $user_id: " . $insert_stmt->error);
                    $message = "Error submitting your request. Please try again: " . $insert_stmt->error;
                    $message_type = "danger";
                }
                $insert_stmt->close();
            }
        }
    }
}

// Close the database connection if it's still open
if ($conn && $conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Account - Assistance Status / Application</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
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
            transition: all 0.2s ease-in-out;
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
            border: none;
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

        .alert-info-custom {
            background-color: #e6f2e6;
            color: #157a0d;
            border-color: #aed5ae;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }

        .alert-info-custom i {
            margin-right: 15px;
            font-size: 1.4rem;
            color: #19860f;
        }

        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }

        .form-label i {
            margin-right: 8px;
            color: #19860f;
        }
    </style>


</head>

<body>
    <nav class="sidebar">
        <a href="ProvincialAgriHome.html" class="header-brand">
            <img src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Province of Antique" />
            <div>Province of Antique</div>
        </a>
        <ul class="nav flex-column">
            <li class="nav-item"><a href="farmer-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="farmer-my_profile.php" class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a></li>
            <!-- Highlighted as the central page for both status and application -->
            <li class="nav-item"><a href="#" class="nav-link active"><i class="fas fa-hand-holding-usd"></i>Apply for Assistance</a></li>
            <li class="nav-item"><a href="farmer-announcement.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li class="nav-item"><a href="farmer-planting_status.php" class="nav-link"><i class="fas fa-leaf"></i> Planting Status</a></li>
            <li class="nav-item"><a href="farmer-progress_tracking.php" class="nav-link"><i class="fas fa-chart-line"></i> Progress Tracking</a></li>
            <li class="nav-item"><a href="farmer-claim_history.php" class="nav-link"><i class="fas fa-history"></i> Claim History</a></li>
        </ul>
    </nav>

    <div class="card-header card-header-custom d-flex justify-content-end align-items-center">
        <span class="me-3">Hi, <strong><?php echo $display_name; ?></strong></span>
        <button class="logout-btn" onclick="location.href='farmers-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content Area -->
    <main>
        <div class="container">

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php if ($message_type == 'success'): ?>
                        <i class="fas fa-check-circle me-2"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php endif; ?>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php 
            // --- CONDITIONAL CONTENT RENDERING ---
            if ($is_approved_unclaimed): 
                // TEMPLATE 1: APPROVED AND UNCLAIMED (Show QR Code)
            ?>
                <div class="card shadow p-4 text-center border-success border-3">
                    <h2 class="text-success mb-3"><i class="fas fa-check-circle"></i> Assistance Approved!</h2>
                    <p class="text-muted mb-4">
                        Your latest approved assistance for **<?php echo date('Y'); ?>** is ready for claiming.
                        Please use your QR code below to claim it at the designated office.
                    </p>
                    <div class="qr-code mb-3">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php echo urlencode($approved_qr_code); ?>&size=220x220" alt="QR Code">
                    </div>
                    <p><strong>Application ID:</strong> <?php echo htmlspecialchars($latest_app_id); ?></p>
                    <p><strong>Farmer ID:</strong> <?php echo htmlspecialchars($farmer_id_display); ?></p>
                    <p><strong>Approval Date:</strong> <?php echo htmlspecialchars($latest_approval_date); ?></p>
                    <p class="text-danger small mt-2">
                        **DO NOT SHARE THIS CODE PUBLICLY.** It is linked to your assistance claim.
                    </p>
                    <button class="btn btn-success col-lg-4 col-md-6 mx-auto mt-3" onclick="downloadQRCode('<?php echo urlencode($approved_qr_code); ?>', '<?php echo htmlspecialchars($farmer_id_display); ?>')">
                        <i class="fas fa-download me-2"></i> Download QR Code
                    </button>
                </div>

            <?php elseif ($application_exists_this_year && $latest_status === 'Pending'): 
                // TEMPLATE 2: PENDING (Show Waiting Message)
            ?>
                <div class="card shadow p-4 text-center border-warning border-3">
                    <h2 class="text-warning mb-3"><i class="fas fa-hourglass-half"></i> Application Pending Review</h2>
                    <p class="text-muted mb-4">
                        Your latest assistance application (ID: **<?php echo htmlspecialchars($latest_app_id); ?>**) for **<?php echo date('Y'); ?>** is currently **Pending** approval from the Provincial Agriculture Office.
                    </p>
                    <p class="card-text text-muted">
                        Please check back later for an update on your status. You cannot submit a new request while one is pending.
                    </p>
                    <i class="fas fa-clock fa-4x text-warning my-4"></i>
                </div>

            <?php else: 
                // TEMPLATE 3: ALLOWED TO APPLY (Show Form) 
                // (Covers: No application this year, Latest is Rejected, Latest is Claimed)
            ?>
                <div class="alert alert-info-custom" role="alert">
                    <i class="fas fa-info-circle"></i>
                    Please fill in the form below to request support. You are currently eligible to apply for new assistance for this year.
                </div>

                <?php if ($application_exists_this_year && ($latest_status === 'Rejected' || $latest_claimed_status == 1)): ?>
                    <div class="alert alert-info border-info" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Note: Your last application (ID: **<?php echo htmlspecialchars($latest_app_id); ?>**) was **<?php echo htmlspecialchars($latest_status); ?>**. You may submit a new request.
                    </div>
                <?php endif; ?>


                <div class="card">
                    <div class="card-header">
                        Assistance Request Form
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label for="assistanceType" class="form-label">
                                    <i class="fas fa-hands-helping"></i>Type of Assistance
                                </label>
                                <select class="form-select" id="assistanceType" name="assistanceType" required>
                                    <option value="">-- Select Assistance --</option>
                                    <option value="Seeds" <?php echo ($assistanceType == 'Seeds') ? 'selected' : ''; ?>>Seeds</option>
                                    <option value="Fertilizer" <?php echo ($assistanceType == 'Fertilizer') ? 'selected' : ''; ?>>Fertilizer</option>
                                    <option value="Fuel" <?php echo ($assistanceType == 'Fuel') ? 'selected' : ''; ?>>Fuel</option>
                                    <option value="Cash Assistance" <?php echo ($assistanceType == 'Cash Assistance') ? 'selected' : ''; ?>>Cash Assistance</option>
                                </select>
                            </div>

                            <!-- Dynamic Seed Details Section -->
                            <div id="seedDetails" class="mb-4" style="display: <?php echo ($assistanceType == 'Seeds') ? 'block' : 'none'; ?>;">
                                <div class="mb-4">
                                    <label for="seedType" class="form-label">
                                        <i class="fas fa-seedling"></i>Seed Type
                                    </label>
                                    <select class="form-select" id="seedType" name="seedType">
                                        <option value="">-- Select Seed Type --</option>
                                        <option value="Hybrid Rice Seeds" <?php echo ($seedType == 'Hybrid Rice Seeds') ? 'selected' : ''; ?>>Hybrid Rice Seeds</option>
                                        <option value="Inbred Rice Seeds" <?php echo ($seedType == 'Inbred Rice Seeds') ? 'selected' : ''; ?>>Inbred Rice Seeds</option>
                                        <option value="Hybrid Corn Seeds" <?php echo ($seedType == 'Hybrid Corn Seeds') ? 'selected' : ''; ?>>Hybrid Corn Seeds</option>
                                        <option value="Vegetable Seeds (Assorted)" <?php echo ($seedType == 'Vegetable Seeds (Assorted)') ? 'selected' : ''; ?>>Vegetable Seeds (Assorted)</option>
                                        <option value="Other" <?php echo ($seedType == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label for="seedQuantity" class="form-label">
                                        <i class="fas fa-boxes"></i>Seed Quantity (e.g., in kg)
                                    </label>
                                    <select class="form-select" id="seedQuantity" name="seedQuantity">
                                        <option value="">-- Select Quantity --</option>
                                        <option value="10kg" <?php echo ($seedQuantity == '10kg') ? 'selected' : ''; ?>>10 kg</option>
                                        <option value="20kg" <?php echo ($seedQuantity == '20kg') ? 'selected' : ''; ?>>20 kg</option>
                                        <option value="25kg" <?php echo ($seedQuantity == '25kg') ? 'selected' : ''; ?>>25 kg</option>
                                        <option value="50kg" <?php echo ($seedQuantity == '50kg') ? 'selected' : ''; ?>>50 kg</option>
                                        <option value="100kg" <?php echo ($seedQuantity == '100kg') ? 'selected' : ''; ?>>100 kg</option>
                                    </select>
                                </div>
                            </div>
                            <!-- End Dynamic Seed Details Section -->

                            <!-- Dynamic Engine Details Section -->
                            <div id="engineDetails" class="mb-4" style="display: <?php echo ($assistanceType == 'Fuel') ? 'block' : 'none'; ?>;">
                                <label for="engineType" class="form-label">
                                    <i class="fas fa-tractor"></i>Engine Type
                                </label>
                                <select class="form-select" id="engineType" name="engineType">
                                    <option value="">-- Select Engine Type --</option>
                                    <option value="Tractor" <?php echo ($engineType == 'Tractor') ? 'selected' : ''; ?>>Tractor</option>
                                    <option value="Water Pump" <?php echo ($engineType == 'Water Pump') ? 'selected' : ''; ?>>Water Pump</option>
                                    <option value="Hand Tractor" <?php echo ($engineType == 'Hand Tractor') ? 'selected' : ''; ?>>Hand Tractor</option>
                                    <option value="Generator" <?php echo ($engineType == 'Generator') ? 'selected' : ''; ?>>Generator</option>
                                    <option value="Harvester" <?php echo ($engineType == 'Harvester') ? 'selected' : ''; ?>>Harvester</option>
                                    <option value="Other" <?php echo ($engineType == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <!-- End Dynamic Engine Details Section -->

                            <div class="mb-4">
                                <label for="remarks" class="form-label">
                                    <i class="fas fa-comment-dots"></i>Remarks / Additional Details
                                </label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="5" placeholder="Explain why you need this assistance, how it will be used, and any other relevant information."><?php echo htmlspecialchars($remarks ?? ''); ?></textarea>
                                <small class="form-text text-muted">Provide a clear explanation to support your request.</small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-theme">
                                    <i class="fas fa-paper-plane"></i>Submit Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php endif; ?>
            <!-- End Conditional Content -->

        </div>
    </main>

    <!-- Bootstrap JS and Custom Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function for QR Code Download (used in Template 1)
        function downloadQRCode(qrData, farmerId) {
            const qrCodeUrl = `https://api.qrserver.com/v1/create-qr-code/?data=${qrData}&size=400x400`;
            const link = document.createElement('a');
            link.href = qrCodeUrl;
            link.download = `Farmer_QRCode_${farmerId || 'Claim'}.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Function for Dynamic Form Fields (used in Template 3)
        document.addEventListener('DOMContentLoaded', function() {
            const assistanceTypeSelect = document.getElementById('assistanceType');
            const seedDetailsDiv = document.getElementById('seedDetails');
            const engineDetailsDiv = document.getElementById('engineDetails');

            if (assistanceTypeSelect) { // Check if form elements exist (only in Template 3)
                function toggleDynamicFields() {
                    // Hide all dynamic sections first
                    seedDetailsDiv.style.display = 'none';
                    engineDetailsDiv.style.display = 'none';

                    // Show relevant section based on selection
                    if (assistanceTypeSelect.value === 'Seeds') {
                        seedDetailsDiv.style.display = 'block';
                    } else if (assistanceTypeSelect.value === 'Fuel') {
                        engineDetailsDiv.style.display = 'block';
                    }
                }

                // Initial check when the page loads (useful if form repopulates on error)
                toggleDynamicFields();

                // Event listener for changes in the assistance type dropdown
                assistanceTypeSelect.addEventListener('change', toggleDynamicFields);
            }
        });
    </script>
</body>

</html>