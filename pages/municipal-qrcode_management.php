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

// Fetch initial data for the recent transactions table
$recent_claims = [];
$stmt_recent = $conn->prepare("
    SELECT
        sc.claim_id,
        sc.claim_date,
        aa.application_id,
        aa.user_id,
        u.name,
        aa.assistance_type,
        aa.status
    FROM subsidy_claims sc
    JOIN assistance_applications aa ON sc.application_id = aa.application_id
    JOIN users u ON aa.user_id = u.user_id
    ORDER BY sc.claim_date DESC
    LIMIT 10
");

if ($stmt_recent) {
    $stmt_recent->execute();
    $result = $stmt_recent->get_result();
    while ($row = $result->fetch_assoc()) {
        // Format Farmer ID
        $row['farmer_id_display'] = "FRM-" . str_pad($row['user_id'], 9, '0', STR_PAD_LEFT);
        $recent_claims[] = $row;
    }
    $stmt_recent->close();
} else {
    error_log("Failed to prepare recent claims statement: " . $conn->error);
}

// Close connection after all initial fetches
if (isset($conn)) {
    $conn->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Municipal Account - QR Code Management</title>
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
    .container-fluid {
        max-width: 1200px; /* Adjust for main content width */
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
    /* Specific styles for QR Code Management */
    .qr-scanner-container {
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        padding: 1rem;
        margin-bottom: 1rem;
        background-color: #fff;
        min-height: 300px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        position: relative;
    }
    #reader {
        width: 100%;
        max-width: 400px; /* Limit scanner width */
        margin-bottom: 1rem;
    }
    .qr-result {
        font-size: 1rem;
        font-weight: 500;
        color: #333;
        margin-top: 1rem;
        text-align: center;
    }
    .qr-result strong {
        color: #19860f;
    }
    #qr-report-table {
        width: 100%;
        margin-top: 1.5rem;
    }
    #qr-report-table th, #qr-report-table td {
        vertical-align: middle;
    }
    .status-badge {
        padding: 0.4em 0.7em;
        border-radius: 0.25rem;
        font-size: 0.8em;
        font-weight: 600;
        color: #fff;
    }
    .status-Claimed { background-color: #28a745; } /* Green */
    .status-Pending { background-color: #ffc107; color: #333; } /* Yellow */
    .status-Rejected { background-color: #dc3545; } /* Red */
    .status-Approved { background-color: #0d6efd; } /* Blue (should not appear here, but for completeness) */
</style>
<!-- html5-qrcode JS for QR Code scanning -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
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
            <a href="municipal-reports_analytics.php" class="nav-link">
                <i class="fas fa-chart-line"></i> Reports & Analytics
            </a>
        </li>
        <li class="nav-item">
            <a href="municipal-qrcode_management.php" class="nav-link active">
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
    <div class="container-fluid">
        <h1 class="page-title">QR Code Management</h1>
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Scan Farmer QR Code</h5>
                    <p class="card-text">
                        Use the camera to scan a farmer's unique QR code for subsidy verification.
                    </p>
                    <div class="qr-scanner-container">
                        <div id="reader" style="width: 100%; max-width: 400px;"></div>
                        <div id="qr-scan-message" class="text-center text-muted" style="display: none;">
                            <i class="fas fa-camera fa-3x mb-3"></i>
                            <p>Click 'Start Scanner' to activate your camera and scan a QR code.</p>
                        </div>
                        <div class="mt-3">
                            <button id="startButton" class="btn btn-theme me-2">
                                <i class="fas fa-play me-1"></i> Start Scanner
                            </button>
                            <button id="stopButton" class="btn btn-secondary" style="display: none;">
                                <i class="fas fa-stop me-1"></i> Stop Scanner
                            </button>
                        </div>
                        <div id="qr-result-display" class="qr-result mt-3" style="display: none;">
                            Scanned Data: <strong id="scannedData"></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Subsidy Claim Verification</h5>
                    <p class="card-text">
                        Details of the scanned QR code and options to verify the claim.
                    </p>
                    <form id="verifyClaimForm">
                        <input type="hidden" id="hiddenFarmerId" name="hiddenFarmerId">
                        <input type="hidden" id="hiddenApplicationId" name="hiddenApplicationId">

                        <div class="mb-3">
                            <label for="applicationIdDisplay" class="form-label">Application ID:</label>
                            <input type="text" class="form-control" id="applicationIdDisplay" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="farmerIdDisplay" class="form-label">Farmer ID (FRM-XXXX):</label>
                            <input type="text" class="form-control" id="farmerIdDisplay" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="farmerName" class="form-label">Farmer Name:</label>
                            <input type="text" class="form-control" id="farmerName" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="subsidyType" class="form-label">Subsidy Type:</label>
                            <input type="text" class="form-control" id="subsidyType" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="claimStatus" class="form-label">Current Status:</label>
                            <input type="text" class="form-control" id="claimStatus" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="claimCount" class="form-label">Claim Count:</label>
                            <input type="text" class="form-control" id="claimCount" readonly>
                        </div>
                        <button type="submit" class="btn btn-theme mt-3" id="verifyButton" disabled>
                            <i class="fas fa-check-circle me-1"></i> Mark as Claimed
                        </button>
                        <div id="verificationMessage" class="mt-3"></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Manual Claim (When Scanner Unavailable)</h5>
                    <p class="card-text">
                        Enter the Farmer ID manually to fetch and claim the subsidy.
                    </p>
                    <form id="manualClaimForm">
                        <div class="mb-3">
                            <label for="manualFarmerId" class="form-label">Farmer ID (e.g., FRM-000000002):</label>
                            <input type="text" class="form-control" id="manualFarmerId" placeholder="FRM-XXXXXXXXX" required>
                        </div>
                        <button type="submit" class="btn btn-theme">
                            <i class="fas fa-search me-1"></i> Fetch Details
                        </button>
                        <div id="manualMessage" class="mt-3"></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Recent QR Claim Transactions</h5>
                    <p class="card-text">A log of recent subsidy claims verified through QR codes.</p>
                    <div class="table-responsive">
                        <table class="table table-hover" id="qr-report-table">
                            <thead>
                                <tr>
                                    <th>Claim Date/Time</th>
                                    <th>Application ID</th>
                                    <th>Farmer ID</th>
                                    <th>Farmer Name</th>
                                    <th>Subsidy Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_claims as $claim): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($claim['claim_date']); ?></td>
                                        <td><?php echo htmlspecialchars($claim['application_id']); ?></td>
                                        <td><?php echo htmlspecialchars($claim['farmer_id_display']); ?></td>
                                        <td><?php echo htmlspecialchars($claim['name']); ?></td>
                                        <td><?php echo htmlspecialchars($claim['assistance_type']); ?></td>
                                        <td><span class="status-badge status-Claimed">Claimed</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</main>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // DOM Elements
    const startButton = document.getElementById('startButton');
    const stopButton = document.getElementById('stopButton');
    const qrScanMessage = document.getElementById('qr-scan-message');
    const qrResultDisplay = document.getElementById('qr-result-display');
    const scannedDataSpan = document.getElementById('scannedData');

    const hiddenFarmerIdInput = document.getElementById('hiddenFarmerId');
    const hiddenApplicationIdInput = document.getElementById('hiddenApplicationId');
    const applicationIdDisplayInput = document.getElementById('applicationIdDisplay');
    const farmerIdDisplayInput = document.getElementById('farmerIdDisplay');
    const farmerNameInput = document.getElementById('farmerName');
    const subsidyTypeInput = document.getElementById('subsidyType');
    const claimStatusInput = document.getElementById('claimStatus');
    const claimCountInput = document.getElementById('claimCount');
    const verifyButton = document.getElementById('verifyButton');
    const verificationMessage = document.getElementById('verificationMessage');
    const verifyClaimForm = document.getElementById('verifyClaimForm');

    let html5QrcodeScanner = null; // html5-qrcode scanner instance
    let isScanning = false;

    // Helper function to reset the verification form
    function resetVerificationForm(message = '', type = 'danger') {
        hiddenFarmerIdInput.value = '';
        hiddenApplicationIdInput.value = '';
        applicationIdDisplayInput.value = '';
        farmerIdDisplayInput.value = '';
        farmerNameInput.value = '';
        subsidyTypeInput.value = '';
        claimStatusInput.value = '';
        claimCountInput.value = '';
        verifyButton.disabled = true;
        verifyButton.textContent = 'Mark as Claimed';
        verifyButton.classList.remove('btn-secondary');
        verifyButton.classList.add('btn-theme');
        verificationMessage.innerHTML = message ? `<div class="alert alert-${type}">${message}</div>` : '';
    }

    // Helper function to stop scanner
    async function stopScanner() {
        if (html5QrcodeScanner && isScanning) {
            try {
                await html5QrcodeScanner.stop();
                await html5QrcodeScanner.clear();
                html5QrcodeScanner = null;
                isScanning = false;
                startButton.style.display = 'inline-block';
                stopButton.style.display = 'none';
                qrScanMessage.style.display = 'block';
                document.getElementById('reader').innerHTML = '';
            } catch (error) {
                console.error('Failed to stop scanner:', error);
            }
        }
    }

    // --- Scanner Control ---
    startButton.addEventListener('click', async () => {
        if (isScanning) {
            return;
        }

        // Reset UI on new scan attempt
        resetVerificationForm();
        qrResultDisplay.style.display = 'none';
        qrScanMessage.style.display = 'none';

        try {
            // Create scanner instance with optimized settings
            html5QrcodeScanner = new Html5Qrcode("reader", {
                verbose: false, // Disable verbose logging for better performance
                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE] // Only QR codes for speed
            });

            // Get available cameras (with timeout to avoid hanging)
            const devices = await Promise.race([
                Html5Qrcode.getCameras(),
                new Promise((_, reject) => setTimeout(() => reject(new Error('Camera detection timeout')), 5000))
            ]);
            
            if (devices && devices.length > 0) {
                // Optimized camera selection - prefer back camera but don't delay
                let cameraId = devices[0].id;
                
                // Quick check for back camera (only check first few devices for speed)
                for (let i = 0; i < Math.min(devices.length, 3); i++) {
                    const label = devices[i].label.toLowerCase();
                    if (label.includes('back') || label.includes('rear') || label.includes('environment')) {
                        cameraId = devices[i].id;
                        break; // Found it, stop searching
                    }
                }
                
                // Start scanning with optimized configuration for speed
                await html5QrcodeScanner.start(
                    cameraId,
                    {
                        fps: 30, // Increased from 10 to 30 for faster scanning
                        qrbox: function(viewfinderWidth, viewfinderHeight) {
                            // Optimized: Use 60% instead of 80% for faster processing
                            // Smaller area = less processing = faster detection
                            let minEdgePercentage = 0.6;
                            let minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
                            let qrboxSize = Math.floor(minEdgeSize * minEdgePercentage);
                            // Ensure minimum size for readability
                            return {
                                width: Math.max(qrboxSize, 200),
                                height: Math.max(qrboxSize, 200)
                            };
                        },
                        aspectRatio: 1.0,
                        // Video constraints for better performance
                        videoConstraints: {
                            facingMode: "environment" // Prefer back camera
                        },
                        // Disable verbose mode for better performance
                        verbose: false
                    },
                    (decodedText, decodedResult) => {
                        // Success callback - QR code detected!
                        console.log('QR Code detected:', decodedText);
                        scannedDataSpan.textContent = decodedText;
                        qrResultDisplay.style.display = 'block';

                        // Process the QR code content
                        processQrData(decodedText);

                        // Stop scanning after one successful scan
                        stopScanner();
                    },
                    (errorMessage) => {
                        // Error callback - ignore scanning errors
                        // Errors are normal during scanning (no QR code in view, etc.)
                        // Only log if it's not a common scanning error
                        if (!errorMessage.includes('NotFoundException') && 
                            !errorMessage.includes('No QR code found')) {
                            // Silent - these are expected during scanning
                        }
                    }
                );

                isScanning = true;
                startButton.style.display = 'none';
                stopButton.style.display = 'inline-block';
                
                // Show success message
                verificationMessage.innerHTML = '<div class="alert alert-info"><i class="fas fa-camera me-2"></i>Scanner active. Point camera at QR code...</div>';
            } else {
                throw new Error('No cameras found. Please ensure your device has a camera and grant camera permissions.');
            }
        } catch (error) {
            console.error('Scanner error:', error);
            let errorMsg = error.message;
            if (error.name === 'NotAllowedError' || error.message.includes('permission')) {
                errorMsg = 'Camera permission denied. Please allow camera access and try again.';
            } else if (error.name === 'NotFoundError') {
                errorMsg = 'No camera found on this device.';
            } else if (error.name === 'NotReadableError') {
                errorMsg = 'Camera is already in use by another application.';
            }
            verificationMessage.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Failed to start scanner: ${errorMsg}</div>`;
            qrScanMessage.style.display = 'block';
            html5QrcodeScanner = null;
            isScanning = false;
        }
    });

    stopButton.addEventListener('click', async () => {
        await stopScanner();
    });
    
    // --- QR Data Parsing & Fetching ---

    function parseQrData(qrData) {
        // Expected format: "app_id:XX&user_id:YY&approved_on:YYYY-MM-DD"
        // Or variations like: "app_id=XX&user_id=YY" (URL encoded)
        const data = {};
        try {
            let parsedString = qrData;
            
            // If it's already in URL format (key=value&key=value), use it directly
            // Otherwise, convert from key:value format to key=value format
            if (!qrData.includes('=') && qrData.includes(':')) {
                // Convert "key:value" pairs to "key=value" format
                // Split by & first, then replace first : with = in each part
                parsedString = qrData.split('&').map(pair => {
                    const colonIndex = pair.indexOf(':');
                    if (colonIndex > 0) {
                        return pair.substring(0, colonIndex) + '=' + pair.substring(colonIndex + 1);
                    }
                    return pair;
                }).join('&');
            }
            
            const urlParams = new URLSearchParams(parsedString);
            data.application_id = urlParams.get('app_id');
            data.user_id = urlParams.get('user_id');
            // approved_on is not strictly needed for the fetch, but good for validation
            
            // Validate parsed data
            if (data.application_id && data.user_id) {
                // Ensure they're valid numbers
                data.application_id = data.application_id.trim();
                data.user_id = data.user_id.trim();
                if (data.application_id && data.user_id) {
                    return data;
                }
            }
            
            console.warn('QR data parsing failed. Raw data:', qrData);
        } catch (e) {
            console.error("Error parsing QR data:", e, "Raw data:", qrData);
        }
        return null;
    }

    // Helper function to automatically save claim to database
    async function autoSaveClaim(appId, userId, farmerName, subsidyType) {
        try {
            verificationMessage.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i> Automatically saving claim to database...</div>';
            
            const response = await fetch('api/update_subsidy_claim.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `application_id=${appId}&user_id=${userId}`
            });
            
            const data = await response.json();

            if (data.success) {
                verificationMessage.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i> ${data.message} Claim automatically saved to database.</div>`;
                claimStatusInput.value = 'Claimed';
                verifyButton.textContent = 'Already Claimed';
                verifyButton.classList.remove('btn-theme');
                verifyButton.classList.add('btn-secondary');
                verifyButton.disabled = true;
                
                // Update claim count
                const newClaimCount = parseInt(claimCountInput.value || 0) + 1;
                claimCountInput.value = newClaimCount;
                
                // Update the recent transactions table dynamically
                addTransactionToTable(appId, userId, farmerName, subsidyType, 'Claimed');
                
                return true;
            } else {
                verificationMessage.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i> ${data.message}</div>`;
                return false;
            }
        } catch (error) {
            console.error('Auto-save claim error:', error);
            verificationMessage.innerHTML = '<div class="alert alert-danger">An error occurred while saving to database. Please try again.</div>';
            return false;
        }
    }

    async function processQrData(qrData) {
        const parsedData = parseQrData(qrData);
        
        if (!parsedData) {
            resetVerificationForm('Invalid QR code data format scanned.');
            return;
        }

        applicationIdDisplayInput.value = parsedData.application_id;
        // Display Farmer ID in FRM-XXXXX format
        farmerIdDisplayInput.value = `FRM-${parsedData.user_id.padStart(9, '0')}`;
        
        // Set hidden fields for form submission
        hiddenApplicationIdInput.value = parsedData.application_id;
        hiddenFarmerIdInput.value = parsedData.user_id;

        // Fetch details from the server
        try {
            const response = await fetch('api/get_subsidy_details.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `application_id=${parsedData.application_id}&user_id=${parsedData.user_id}`
            });

            const data = await response.json();

            if (data.success && data.details) {
                const details = data.details;
                farmerNameInput.value = details.farmer_name;
                subsidyTypeInput.value = details.subsidy_type;
                claimStatusInput.value = details.current_status;
                claimCountInput.value = details.claim_count;

                if (details.claim_count > 0) {
                    resetVerificationForm('This subsidy has already been claimed. The QR code cannot be scanned again unless the farmer applies for a new subsidy.', 'danger');
                } else if (details.current_status === 'Approved' || details.current_status === 'Claimed') {
                    // Automatically save the claim to database
                    await autoSaveClaim(
                        parsedData.application_id,
                        parsedData.user_id,
                        details.farmer_name,
                        details.subsidy_type
                    );
                } else {
                    resetVerificationForm(`Subsidy status is '${details.current_status}'. Only 'Approved' or 'Claimed' applications can be claimed.`, 'warning');
                }
            } else {
                resetVerificationForm(data.message || 'Error fetching subsidy details from the server.');
            }

        } catch (error) {
            console.error('Fetch error:', error);
            resetVerificationForm('An error occurred while connecting to the server.');
        }
    }
    
    // --- Claim Submission ---

    verifyClaimForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const appId = hiddenApplicationIdInput.value;
        const userId = hiddenFarmerIdInput.value;
        const currentStatus = claimStatusInput.value; // Check current visible status

        if (!appId || !userId || verifyButton.disabled || (currentStatus !== 'Approved' && currentStatus !== 'Claimed')) {
            verificationMessage.innerHTML = '<div class="alert alert-danger">Cannot process claim. Invalid data or status not eligible for claiming.</div>';
            return;
        }

        // Confirm before submission
        if (!confirm('Are you sure you want to MARK THIS SUBSIDY AS CLAIMED? This action cannot be undone.')) {
            return;
        }

        verificationMessage.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i> Processing claim...</div>';
        verifyButton.disabled = true; // Disable to prevent double submission

        try {
            const response = await fetch('api/update_subsidy_claim.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `application_id=${appId}&user_id=${userId}`
            });
            
            const data = await response.json();

            if (data.success) {
                verificationMessage.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i> ${data.message}</div>`;
                claimStatusInput.value = 'Claimed';
                verifyButton.textContent = 'Already Claimed';
                verifyButton.classList.remove('btn-theme');
                verifyButton.classList.add('btn-secondary');
                
                // Update the recent transactions table dynamically
                addTransactionToTable(appId, userId, farmerNameInput.value, subsidyTypeInput.value, 'Claimed');

            } else {
                verificationMessage.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i> ${data.message}</div>`;
                verifyButton.disabled = false; // Re-enable if it was a claim-specific error
            }
        } catch (error) {
            console.error('Claim submission error:', error);
            verificationMessage.innerHTML = '<div class="alert alert-danger">An internal server error occurred during verification.</div>';
            verifyButton.disabled = false;
        }
    });

    // --- Table Update ---

    function addTransactionToTable(appId, userId, farmerName, subsidyType, status) {
        const tableBody = document.querySelector('#qr-report-table tbody');
        const newRow = tableBody.insertRow(0); // Add to the top

        const dateTimeCell = newRow.insertCell(0);
        const appIdCell = newRow.insertCell(1);
        const farmerIdCell = newRow.insertCell(2);
        const farmerNameCell = newRow.insertCell(3);
        const subsidyTypeCell = newRow.insertCell(4);
        const statusCell = newRow.insertCell(5);

        const now = new Date();
        const farmerIdDisplay = `FRM-${String(userId).padStart(9, '0')}`;
        
        dateTimeCell.textContent = now.toLocaleString();
        appIdCell.textContent = appId;
        farmerIdCell.textContent = farmerIdDisplay;
        farmerNameCell.textContent = farmerName;
        subsidyTypeCell.textContent = subsidyType;
        statusCell.innerHTML = `<span class="status-badge status-${status}">${status}</span>`;
    }
    
    // --- Manual Claim Form Handler ---
    const manualClaimForm = document.getElementById('manualClaimForm');
    const manualFarmerIdInput = document.getElementById('manualFarmerId');
    const manualMessage = document.getElementById('manualMessage');

    manualClaimForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const farmerIdInput = manualFarmerIdInput.value.trim();
        
        if (!farmerIdInput) {
            manualMessage.innerHTML = '<div class="alert alert-danger">Please enter a Farmer ID.</div>';
            return;
        }

        // Extract user_id from FRM-XXXXXXXXX format
        let userId = null;
        if (farmerIdInput.startsWith('FRM-')) {
            const idPart = farmerIdInput.substring(4);
            userId = parseInt(idPart, 10);
            if (isNaN(userId)) {
                manualMessage.innerHTML = '<div class="alert alert-danger">Invalid Farmer ID format. Please use FRM-XXXXXXXXX format.</div>';
                return;
            }
        } else {
            // Try to parse as direct number
            userId = parseInt(farmerIdInput, 10);
            if (isNaN(userId)) {
                manualMessage.innerHTML = '<div class="alert alert-danger">Invalid Farmer ID format. Please use FRM-XXXXXXXXX format.</div>';
                return;
            }
        }

        manualMessage.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i> Fetching details...</div>';

        try {
            // First, we need to find the application_id for this user
            // We'll need to create an API endpoint or modify the existing one to accept just user_id
            // For now, let's try to fetch using a modified approach
            const response = await fetch('api/get_subsidy_details.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `user_id=${userId}`
            });

            const data = await response.json();

            if (data.success && data.details) {
                const details = data.details;
                
                // Populate the verification form
                hiddenApplicationIdInput.value = details.application_id;
                hiddenFarmerIdInput.value = details.farmer_id;
                applicationIdDisplayInput.value = details.application_id;
                farmerIdDisplayInput.value = `FRM-${String(details.farmer_id).padStart(9, '0')}`;
                farmerNameInput.value = details.farmer_name;
                subsidyTypeInput.value = details.subsidy_type;
                claimStatusInput.value = details.current_status;
                claimCountInput.value = details.claim_count;

                if (details.current_status === 'Approved' || details.current_status === 'Claimed') {
                    verifyButton.disabled = false;
                    verifyButton.textContent = 'Mark as Claimed';
                    verificationMessage.innerHTML = '<div class="alert alert-info">Details fetched successfully. You can now mark as claimed.</div>';
                    manualMessage.innerHTML = '<div class="alert alert-success">Details fetched successfully!</div>';
                } else {
                    verificationMessage.innerHTML = `<div class="alert alert-warning">Subsidy status is '${details.current_status}'. Only 'Approved' or 'Claimed' applications can be claimed.</div>`;
                    manualMessage.innerHTML = `<div class="alert alert-warning">Subsidy status is '${details.current_status}'. Only 'Approved' or 'Claimed' applications can be claimed.</div>`;
                }
            } else {
                manualMessage.innerHTML = `<div class="alert alert-danger">${data.message || 'No matching subsidy found for this Farmer ID.'}</div>`;
            }
        } catch (error) {
            console.error('Manual fetch error:', error);
            manualMessage.innerHTML = '<div class="alert alert-danger">An error occurred while fetching details from the server.</div>';
        }
    });
    
    // Initial state
    document.addEventListener('DOMContentLoaded', () => {
        resetVerificationForm();
    });

</script>
</body>
</html>