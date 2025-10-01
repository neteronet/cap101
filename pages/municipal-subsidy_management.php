<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Account - Subsidy Management</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

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
            max-width: 1200px;
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

        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.4rem;
            font-size: 13px;
            font-weight: 500;
        }

        .status-pending {
            background-color: #ffc107;
            color: #856404;
        }

        .status-approved {
            background-color: #28a745;
            color: #fff;
        }

        .status-rejected {
            background-color: #dc3545;
            color: #fff;
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
            padding: 0.3rem 0.6rem;
        }

        .badge {
            font-size: 13px;
            font-weight: 500;
            padding: 0.4em 0.6em;
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
                <a href="municipal-subsidy_management.php" class="nav-link active">
                    <i class="fas fa-hand-holding-usd"></i> Subsidy Management
                </a>
            </li>
            <li class="nav-item">
                <a href="municipal-announcements.php" class="nav-link">
                    <i class="fas fa-bullhorn"></i> Announcements
                </a>
            </li>
            <li class="nav-item">
                <a href="municipal-qrcode_management.php" class="nav-link">
                    <i class="fas fa-qrcode"></i> QR Code Management
                </a>
            </li>
        </ul>
    </nav>

    <!-- Header -->
    <div class="card-header card-header-custom d-flex justify-content-end align-items-center">
        <span class="me-3">Hi, <strong>username</strong></span>
        <button class="logout-btn" onclick="location.href='municipal-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h1 class="page-title">Subsidy Management</h1>
            <p class="text-muted mb-4">Validate and process subsidy requests submitted by farmers.</p>

            <!-- Subsidy Requests Table -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="subsidyTable">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Farmer Name</th>
                                    <th>Barangay</th>
                                    <th>Crop</th>
                                    <th>Requested Subsidy</th>
                                    <th>Status</th>
                                    <th>QR Code</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Sample Request 1 -->
                                <tr>
                                    <td>1</td>
                                    <td>Juan Dela Cruz</td>
                                    <td>Barangay Uno</td>
                                    <td>Rice</td>
                                    <td>Seeds</td>
                                    <td><span class="badge status-pending">Pending</span></td>
                                    <td id="qr-1">—</td>
                                    <td>
                                        <button class="btn btn-sm btn-success" onclick="approveRequest(1)"><i class="fas fa-check me-1"></i>Approve</button>
                                        <button class="btn btn-sm btn-danger" onclick="rejectRequest(1)"><i class="fas fa-times me-1"></i>Reject</button>
                                    </td>
                                </tr>
                                <!-- Sample Request 2 -->
                                <tr>
                                    <td>2</td>
                                    <td>Maria Santos</td>
                                    <td>Barangay Dos</td>
                                    <td>Corn</td>
                                    <td>Fertilizer</td>
                                    <td><span class="badge status-approved">Approved</span></td>
                                    <td><img src="https://api.qrserver.com/v1/create-qr-code/?data=MariaSantos2025&size=50x50" alt="QR Code" class="img-fluid"></td>
                                    <td>
                                        <button class="btn btn-sm btn-secondary" disabled><i class="fas fa-check me-1"></i>Approved</button>
                                    </td>
                                </tr>
                                <!-- Sample Request 3 -->
                                <tr>
                                    <td>3</td>
                                    <td>Pedro Gomez</td>
                                    <td>Barangay Tres</td>
                                    <td>Rice</td>
                                    <td>Seeds + Fertilizer</td>
                                    <td><span class="badge status-rejected">Rejected</span></td>
                                    <td>—</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="sendBackForReview(3)"><i class="fas fa-undo me-1"></i>Send Back</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function approveRequest(id) {
            const row = document.querySelector(`#subsidyTable tbody tr:nth-child(${id})`);
            const statusCell = row.children[5];
            const qrCell = row.children[6];

            // Update status
            statusCell.innerHTML = '<span class="badge status-approved">Approved</span>';

            // Generate sample QR code
            const farmerName = row.children[1].textContent.replace(/\s+/g, '');
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?data=${farmerName}2025&size=50x50`;
            qrCell.innerHTML = `<img src="${qrUrl}" alt="QR Code" class="img-fluid">`;

            // Disable buttons
            const actionCell = row.children[7];
            actionCell.innerHTML = '<button class="btn btn-sm btn-secondary" disabled><i class="fas fa-check me-1"></i>Approved</button>';

            alert("Subsidy approved and QR code generated.");
        }

        function rejectRequest(id) {
            const row = document.querySelector(`#subsidyTable tbody tr:nth-child(${id})`);
            const statusCell = row.children[5];
            const qrCell = row.children[6];

            statusCell.innerHTML = '<span class="badge status-rejected">Rejected</span>';
            qrCell.textContent = '—';

            const actionCell = row.children[7];
            actionCell.innerHTML = `<button class="btn btn-sm btn-outline-primary" onclick="sendBackForReview(${id})"><i class="fas fa-undo me-1"></i>Send Back</button>`;

            alert("Subsidy request rejected.");
        }

        function sendBackForReview(id) {
            const row = document.querySelector(`#subsidyTable tbody tr:nth-child(${id})`);
            const statusCell = row.children[5];
            const qrCell = row.children[6];

            statusCell.innerHTML = '<span class="badge status-pending">Pending</span>';
            qrCell.textContent = '—';

            const actionCell = row.children[7];
            actionCell.innerHTML = `
            <button class="btn btn-sm btn-success" onclick="approveRequest(${id})"><i class="fas fa-check me-1"></i>Approve</button>
            <button class="btn btn-sm btn-danger" onclick="rejectRequest(${id})"><i class="fas fa-times me-1"></i>Reject</button>
        `;

            alert("Returned to pending for review.");
        }
    </script>

</body>

</html>