<?php
session_start();

// Check if the user is logged in. If not, redirect to the login page.
// Adjust 'municipal-login.php' to your actual login page filename and path if different.
if (!isset($_SESSION['user_id'])) {
    header("location: municipal-login.php");
    exit();
}

// Retrieve the user's name from the session.
$display_name = $_SESSION['name'] ?? 'Mao'; // Fallback to 'Mao' if not set

$servername = "localhost";
$db_username = "root"; // Your database username
$db_password = "";     // Your database password
$dbname = "cap101"; // Your database name

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
} else {
    // Fetch the name from the database based on the user_id in the session
    $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($fetched_db_name);
    $stmt->fetch();
    if ($fetched_db_name) {
        $display_name = $fetched_db_name; // Use the name fetched from DB
    }
    $stmt->close();
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
        .status-claimed { background-color: #28a745; } /* Green */
        .status-pending { background-color: #ffc107; color: #333; } /* Yellow */
        .status-not-claimed { background-color: #dc3545; } /* Red */
    </style>
    <!-- Instascan JS for QR Code scanning -->
    <!-- You might need to self-host this or use a modern QR scanner library if Instascan is too old/problematic -->
    <!-- For a more robust solution, consider libraries like html5-qrcode or jsqr -->
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
                                <div class="mb-3">
                                    <label for="farmerId" class="form-label">Farmer ID:</label>
                                    <input type="text" class="form-control" id="farmerId" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="subsidyId" class="form-label">Subsidy ID:</label>
                                    <input type="text" class="form-control" id="subsidyId" readonly>
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
                                            <th>Date/Time</th>
                                            <th>Farmer ID</th>
                                            <th>Farmer Name</th>
                                            <th>Subsidy ID</th>
                                            <th>Subsidy Type</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Dynamic rows will be added here via JavaScript/AJAX -->
                                        <tr>
                                            <td>2023-10-26 10:30 AM</td>
                                            <td>FARM-001</td>
                                            <td>Juan dela Cruz</td>
                                            <td>SUB-RICE-001</td>
                                            <td>Rice Seeds</td>
                                            <td><span class="status-claimed">Claimed</span></td>
                                        </tr>
                                        <tr>
                                            <td>2023-10-25 02:15 PM</td>
                                            <td>FARM-003</td>
                                            <td>Maria Clara</td>
                                            <td>SUB-FERT-002</td>
                                            <td>Fertilizer</td>
                                            <td><span class="status-pending">Pending Verification</span></td>
                                        </tr>
                                        <tr>
                                            <td>2023-10-24 09:00 AM</td>
                                            <td>FARM-002</td>
                                            <td>Pedro Reyes</td>
                                            <td>SUB-FUEL-001</td>
                                            <td>Fuel Subsidy</td>
                                            <td><span class="status-claimed">Claimed</span></td>
                                        </tr>
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
        const video = document.getElementById('preview');
        const startButton = document.getElementById('startButton');
        const stopButton = document.getElementById('stopButton');
        const qrScanMessage = document.getElementById('qr-scan-message');
        const qrResultDisplay = document.getElementById('qr-result-display');
        const scannedDataSpan = document.getElementById('scannedData');

        const farmerIdInput = document.getElementById('farmerId');
        const subsidyIdInput = document.getElementById('subsidyId');
        const farmerNameInput = document.getElementById('farmerName');
        const subsidyTypeInput = document.getElementById('subsidyType');
        const claimStatusInput = document.getElementById('claimStatus');
        const verifyButton = document.getElementById('verifyButton');
        const verificationMessage = document.getElementById('verificationMessage');

        let scanner; // Declare scanner in a broader scope

        startButton.addEventListener('click', () => {
            if (scanner) {
                scanner.stop(); // Stop any existing scanner
            }

            scanner = new Instascan.Scanner({ video: video, scanPeriod: 5 });

            scanner.addListener('scan', function (content) {
                console.log('Scanned:', content);
                scannedDataSpan.textContent = content;
                qrResultDisplay.style.display = 'block';
                populateVerificationForm(content);
                scanner.stop(); // Stop scanning after one successful scan
                video.style.display = 'none';
                qrScanMessage.style.display = 'block';
                startButton.style.display = 'block';
                stopButton.style.display = 'none';
            });

            Instascan.Camera.getCameras().then(function (cameras) {
                if (cameras.length > 0) {
                    // You might want to let the user select a camera if multiple are available
                    scanner.start(cameras[0]);
                    video.style.display = 'block';
                    qrScanMessage.style.display = 'none';
                    startButton.style.display = 'none';
                    stopButton.style.display = 'block';
                } else {
                    alert('No cameras found.');
                    console.error('No cameras found.');
                }
            }).catch(function (e) {
                console.error(e);
                alert('Error accessing camera. Please ensure permissions are granted.');
                startButton.style.display = 'block'; // Show start button again if error
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

        function populateVerificationForm(qrData) {
            // This is where you would parse the QR data and ideally
            // make an AJAX call to your server to fetch actual farmer/subsidy details
            // based on the farmer ID and subsidy ID in the QR code.

            // For demonstration, we'll parse a simple string.
            // Expected format: "FarmerID:FARM-001, SubsidyID:SUB-RICE-001"
            const dataParts = qrData.split(',').map(part => part.trim());
            let farmerID = '';
            let subsidyID = '';

            dataParts.forEach(part => {
                if (part.startsWith('FarmerID:')) {
                    farmerID = part.substring('FarmerID:'.length);
                } else if (part.startsWith('SubsidyID:')) {
                    subsidyID = part.substring('SubsidyID:'.length);
                }
            });

            farmerIdInput.value = farmerID;
            subsidyIdInput.value = subsidyID;

            // Simulate fetching details from a database
            // In a real app, this would be an AJAX call
            if (farmerID && subsidyID) {
                fetchFarmerAndSubsidyDetails(farmerID, subsidyID);
            } else {
                farmerNameInput.value = 'Invalid QR Data';
                subsidyTypeInput.value = '';
                claimStatusInput.value = '';
                verifyButton.disabled = true;
                verificationMessage.innerHTML = '<div class="alert alert-danger">Invalid QR code data scanned.</div>';
            }
        }

        function fetchFarmerAndSubsidyDetails(farmerID, subsidyID) {
            // This is a placeholder for an AJAX call to your backend (e.g., 'get_subsidy_details.php')
            // which would query your database.

            // Simulate database response
            const mockDb = {
                'FARM-001': { name: 'Juan dela Cruz' },
                'FARM-002': { name: 'Pedro Reyes' },
                'FARM-003': { name: 'Maria Clara' }
            };

            const mockSubsidies = {
                'SUB-RICE-001': { type: 'Rice Seeds', status: 'Pending' },
                'SUB-FERT-002': { type: 'Fertilizer', status: 'Approved' },
                'SUB-FUEL-001': { type: 'Fuel Subsidy', status: 'Claimed' }
            };

            const farmer = mockDb[farmerID];
            const subsidy = mockSubsidies[subsidyID];

            if (farmer && subsidy) {
                farmerNameInput.value = farmer.name;
                subsidyTypeInput.value = subsidy.type;
                claimStatusInput.value = subsidy.status;
                if (subsidy.status !== 'Claimed') {
                    verifyButton.disabled = false;
                    verifyButton.textContent = 'Mark as Claimed';
                    verifyButton.classList.remove('btn-secondary');
                    verifyButton.classList.add('btn-theme');
                } else {
                    verifyButton.disabled = true;
                    verifyButton.textContent = 'Already Claimed';
                    verifyButton.classList.remove('btn-theme');
                    verifyButton.classList.add('btn-secondary');
                    verificationMessage.innerHTML = '<div class="alert alert-warning">This subsidy has already been claimed.</div>';
                }
            } else {
                farmerNameInput.value = 'Not Found';
                subsidyTypeInput.value = 'Not Found';
                claimStatusInput.value = 'N/A';
                verifyButton.disabled = true;
                verificationMessage.innerHTML = '<div class="alert alert-danger">Farmer or Subsidy not found in the system.</div>';
            }
        }


        verifyClaimForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const farmerId = farmerIdInput.value;
            const subsidyId = subsidyIdInput.value;

            if (!farmerId || !subsidyId || verifyButton.disabled) {
                verificationMessage.innerHTML = '<div class="alert alert-danger">Cannot process claim. Invalid data or already claimed.</div>';
                return;
            }

            // In a real application, you'd send an AJAX request to update the subsidy status in the database.
            // Example:
            /*
            fetch('update_subsidy_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ farmer_id: farmerId, subsidy_id: subsidyId, status: 'Claimed' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    verificationMessage.innerHTML = '<div class="alert alert-success">Subsidy successfully marked as claimed!</div>';
                    claimStatusInput.value = 'Claimed';
                    verifyButton.disabled = true;
                    verifyButton.textContent = 'Already Claimed';
                    verifyButton.classList.remove('btn-theme');
                    verifyButton.classList.add('btn-secondary');
                    // Refresh the recent transactions table
                    addTransactionToTable(farmerId, farmerNameInput.value, subsidyId, subsidyTypeInput.value, 'Claimed');
                } else {
                    verificationMessage.innerHTML = `<div class="alert alert-danger">Error claiming subsidy: ${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                verificationMessage.innerHTML = '<div class="alert alert-danger">An error occurred during verification.</div>';
            });
            */

            // Simulate success
            verificationMessage.innerHTML = '<div class="alert alert-success">Subsidy successfully marked as claimed!</div>';
            claimStatusInput.value = 'Claimed';
            verifyButton.disabled = true;
            verifyButton.textContent = 'Already Claimed';
            verifyButton.classList.remove('btn-theme');
            verifyButton.classList.add('btn-secondary');
            addTransactionToTable(farmerId, farmerNameInput.value, subsidyId, subsidyTypeInput.value, 'Claimed');
        });

        function addTransactionToTable(farmerId, farmerName, subsidyId, subsidyType, status) {
            const tableBody = document.querySelector('#qr-report-table tbody');
            const newRow = tableBody.insertRow(0); // Add to the top

            const dateTimeCell = newRow.insertCell(0);
            const farmerIdCell = newRow.insertCell(1);
            const farmerNameCell = newRow.insertCell(2);
            const subsidyIdCell = newRow.insertCell(3);
            const subsidyTypeCell = newRow.insertCell(4);
            const statusCell = newRow.insertCell(5);

            const now = new Date();
            dateTimeCell.textContent = now.toLocaleString();
            farmerIdCell.textContent = farmerId;
            farmerNameCell.textContent = farmerName;
            subsidyIdCell.textContent = subsidyId;
            subsidyTypeCell.textContent = subsidyType;
            statusCell.innerHTML = `<span class="status-badge status-${status.toLowerCase().replace(/\s/g, '-')}}">${status}</span>`;
        }

        // Initial state for the verification form
        farmerIdInput.value = '';
        subsidyIdInput.value = '';
        farmerNameInput.value = '';
        subsidyTypeInput.value = '';
        claimStatusInput.value = '';
        verifyButton.disabled = true;
        verificationMessage.innerHTML = '';
    </script>
</body>
</html>