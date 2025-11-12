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
        aa.application_id,
        aa.claimed_date,
        aa.user_id,
        u.name,
        aa.assistance_type,
        aa.status
    FROM assistance_applications aa
    JOIN users u ON aa.user_id = u.user_id
    WHERE aa.claimed = 1
    ORDER BY aa.claimed_date DESC
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
    <!-- Instascan JS for QR Code scanning -->
    <script src="https://rawgit.com/schmich/instascan-js/master/docs/bundle.js"></script>
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
                                <video id="preview" style="width: 100%; max-width: 400px; display: none;"></video>
                                <div id="qr-scan-message" class="text-center text-muted">
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
                                <button type="submit" class="btn btn-theme mt-3" id="verifyButton" disabled>
                                    <i class="fas fa-check-circle me-1"></i> Mark as Claimed
                                </button>
                                <div id="verificationMessage" class="mt-3"></div>
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
                                                <td><?php echo htmlspecialchars($claim['claimed_date']); ?></td>
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
        const video = document.getElementById('preview');
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
        const verifyButton = document.getElementById('verifyButton');
        const verificationMessage = document.getElementById('verificationMessage');
        const verifyClaimForm = document.getElementById('verifyClaimForm');

        let scanner; // Instascan scanner instance

        // Helper function to reset the verification form
        function resetVerificationForm(message = '', type = 'danger') {
            hiddenFarmerIdInput.value = '';
            hiddenApplicationIdInput.value = '';
            applicationIdDisplayInput.value = '';
            farmerIdDisplayInput.value = '';
            farmerNameInput.value = '';
            subsidyTypeInput.value = '';
            claimStatusInput.value = '';
            verifyButton.disabled = true;
            verifyButton.textContent = 'Mark as Claimed';
            verifyButton.classList.remove('btn-secondary');
            verifyButton.classList.add('btn-theme');
            verificationMessage.innerHTML = message ? `<div class="alert alert-${type}">${message}</div>` : '';
        }

        // --- Scanner Control ---
        startButton.addEventListener('click', () => {
            if (scanner) {
                scanner.stop(); // Stop any existing scanner
            }

            // Reset UI on new scan attempt
            resetVerificationForm();
            qrResultDisplay.style.display = 'none';

            scanner = new Instascan.Scanner({ video: video, scanPeriod: 5 });

            scanner.addListener('scan', function (content) {
                console.log('Scanned:', content);
                scannedDataSpan.textContent = content;
                qrResultDisplay.style.display = 'block';
                
                // Process the QR code content
                processQrData(content);

                // Stop scanning after one successful scan
                if (scanner) scanner.stop(); 
                video.style.display = 'none';
                qrScanMessage.style.display = 'block';
                startButton.style.display = 'block';
                stopButton.style.display = 'none';
            });

            Instascan.Camera.getCameras().then(function (cameras) {
                if (cameras.length > 0) {
                    scanner.start(cameras[0]);
                    video.style.display = 'block';
                    qrScanMessage.style.display = 'none';
                    startButton.style.display = 'none';
                    stopButton.style.display = 'block';
                } else {
                    alert('No cameras found.');
                    console.error('No cameras found.');
                    startButton.style.display = 'block';
                    stopButton.style.display = 'none';
                }
            }).catch(function (e) {
                console.error(e);
                alert('Error accessing camera. Please ensure permissions are granted.');
                startButton.style.display = 'block'; 
                stopButton.style.display = 'none';
            });
        });

        stopButton.addEventListener('click', () => {
            if (scanner) {
                scanner.stop();
                video.style.display = 'none';
                qrScanMessage.style.display = 'block';
                startButton.style.display = 'block';
                stopButton.style.display = 'none';
            }
        });
        
        // --- QR Data Parsing & Fetching ---

        function parseQrData(qrData) {
            // Expected format: "app_id:XX&user_id:YY&approved_on:YYYY-MM-DD"
            const data = {};
            try {
                // Use URLSearchParams to correctly parse the '&' separated key:value pairs
                // First, replace ':' with '=' so URLSearchParams can interpret them as key=value
                const urlParams = new URLSearchParams(qrData.replace(/:/g, '='));
                data.application_id = urlParams.get('app_id');
                data.user_id = urlParams.get('user_id');
                // approved_on is not strictly needed for the fetch, but good for validation
                
                // Validate parsed data
                if (data.application_id && data.user_id) {
                    return data;
                }
            } catch (e) {
                console.error("Error parsing QR data:", e);
            }
            return null;
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
                // CORRECTED PATH: Assumes the API folder is one level up from the current script.
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

                    if (details.current_status === 'Approved' && details.is_claimed == 0) {
                        verifyButton.disabled = false;
                        verifyButton.textContent = 'Mark as Claimed';
                        verificationMessage.innerHTML = '<div class="alert alert-info">Verification successful. Proceed to mark as claimed.</div>';
                    } else if (details.is_claimed == 1) {
                        // Use a local reset but keep the claimed status for visibility
                        resetVerificationForm('This subsidy has ALREADY BEEN CLAIMED.', 'warning');
                        claimStatusInput.value = 'Claimed'; 
                        verifyButton.textContent = 'Already Claimed';
                        verifyButton.classList.remove('btn-theme');
                        verifyButton.classList.add('btn-secondary');
                    } else if (details.current_status !== 'Approved') {
                        resetVerificationForm(`Subsidy status is '${details.current_status}'. Only 'Approved' applications can be claimed.`, 'warning');
                    } else {
                        // Catch-all for weird states (e.g., claimed = 0 but status is not Approved/Pending/Rejected)
                        resetVerificationForm('Subsidy status check inconclusive.', 'warning');
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

            if (!appId || !userId || verifyButton.disabled || currentStatus !== 'Approved') {
                verificationMessage.innerHTML = '<div class="alert alert-danger">Cannot process claim. Invalid data, already claimed, or not approved.</div>';
                return;
            }

            // Confirm before submission
            if (!confirm('Are you sure you want to MARK THIS SUBSIDY AS CLAIMED? This action cannot be undone.')) {
                return;
            }

            verificationMessage.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i> Processing claim...</div>';
            verifyButton.disabled = true; // Disable to prevent double submission

            try {
                // CORRECTED PATH: Assumes the API folder is one level up from the current script.
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
        
        // Initial state
        document.addEventListener('DOMContentLoaded', () => {
            resetVerificationForm();
        });

    </script>
</body>
</html>