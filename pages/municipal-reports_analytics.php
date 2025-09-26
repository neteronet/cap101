<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Agri - Reports & Analytics</title>

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
            transition: background-color 0.2s ease;
        }

        .btn-theme:hover {
            background-color: #146c0b;
            color: #fff;
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

        .form-label {
            font-weight: 500;
            color: #555;
            font-size: 0.95rem;
        }

        .form-select,
        .form-control {
            border-radius: 0.25rem;
            border: 1px solid #ced4da;
            padding: 0.5rem 0.75rem;
            font-size: 0.95rem;
        }

        .alert-info {
            background-color: #e9f5ee; /* Light green */
            color: #19860f;
            border-color: #19860f;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-color: #ffc107;
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
                <a href="municipal-subsidy_management.php" class="nav-link">
                    <i class="fas fa-hand-holding-usd"></i> Subsidy Management
                </a>
            </li>
            <li class="nav-item">
                <a href="municipal-reports_anaytics.php" class="nav-link active">
                    <i class="fas fa-chart-bar"></i> Reports & Analytics
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
            <h1 class="page-title">Reports & Analytics</h1>
            <p class="text-muted mb-4">Generate and view progress reports and disaster impact data.</p>

            <div class="row g-4">
                <!-- Generate Progress Reports -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-chart-line me-2"></i>Progress Reports</h5>
                            <form id="progressReportForm">
                                <div class="mb-3">
                                    <label for="cropSelect" class="form-label">Select Crop</label>
                                    <select class="form-select" id="cropSelect" required>
                                        <option value="">Choose crop...</option>
                                        <option value="rice">Rice</option>
                                        <option value="corn">Corn</option>
                                        <option value="vegetables">Vegetables</option>
                                        <option value="fruits">Fruits</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="barangaySelect" class="form-label">Select Barangay</label>
                                    <select class="form-select" id="barangaySelect" required>
                                        <option value="">Choose barangay...</option>
                                        <option value="Barangay1">Barangay 1</option>
                                        <option value="Barangay2">Barangay 2</option>
                                        <option value="Barangay3">Barangay 3</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="seasonSelect" class="form-label">Select Season</label>
                                    <select class="form-select" id="seasonSelect" required>
                                        <option value="">Choose season...</option>
                                        <option value="dry">Dry Season</option>
                                        <option value="wet">Wet Season</option>
                                        <option value="planting">Planting Season</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-theme">Generate Report</button>
                            </form>
                            <div class="mt-3" id="progressReportResult"></div>
                        </div>
                    </div>
                </div>

                <!-- Disaster Impact Reports -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-house-damage me-2"></i>Disaster Impact Reports</h5>
                            <form id="disasterReportForm">
                                <div class="mb-3">
                                    <label for="disasterType" class="form-label">Select Disaster Type</label>
                                    <select class="form-select" id="disasterType" required>
                                        <option value="">Choose disaster...</option>
                                        <option value="flood">Flood</option>
                                        <option value="typhoon">Typhoon</option>
                                        <option value="drought">Drought</option>
                                        <option value="earthquake">Earthquake</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="disasterBarangay" class="form-label">Select Barangay</label>
                                    <select class="form-select" id="disasterBarangay" required>
                                        <option value="">Choose barangay...</option>
                                        <option value="Barangay1">Barangay 1</option>
                                        <option value="Barangay2">Barangay 2</option>
                                        <option value="Barangay3">Barangay 3</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-theme">Generate Disaster Report</button>
                            </form>
                            <div class="mt-3" id="disasterReportResult"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Simple JavaScript to simulate report generation -->
    <script>
        document.getElementById('progressReportForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const crop = document.getElementById('cropSelect').value;
            const barangay = document.getElementById('barangaySelect').value;
            const season = document.getElementById('seasonSelect').value;
            const resultDiv = document.getElementById('progressReportResult');
            resultDiv.innerHTML = `<div class="alert alert-info">Generating progress report for <strong>${crop}</strong> in <strong>${barangay}</strong> during <strong>${season}</strong> season...</div>`;
            // Here you can add AJAX to fetch real data
        });

        document.getElementById('disasterReportForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const disaster = document.getElementById('disasterType').value;
            const barangay = document.getElementById('disasterBarangay').value;
            const resultDiv = document.getElementById('disasterReportResult');
            resultDiv.innerHTML = `<div class="alert alert-warning">Generating disaster impact report for <strong>${disaster}</strong> in <strong>${barangay}</strong>...</div>`;
            // Here you can add AJAX to fetch real data
        });
    </script>
</body>

</html>