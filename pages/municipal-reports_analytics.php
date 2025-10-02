<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Account - Reports & Analytics</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Chart.js for Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom Styles (from your provided code) -->
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
            border: none;
            transition: all 0.3s ease;
        }

        .btn-theme:hover {
            background-color: #146c0b;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        main {
            margin-left: 250px;
            padding: 1rem 2rem 2rem 2rem;
            padding-top: 72px;
            background: #f8f9fa;
            min-height: 100vh;
        }

        .container-fluid {
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

        .card-body h6 {
            font-size: 14px;
            color: #6c757d;
        }

        .card-body h2 {
            font-size: 2rem;
            margin-top: 5px;
            font-weight: 700;
            color: #19860f;
        }

        .card-body h2.text-warning {
            color: #ffc107 !important;
        }

        .card-body .btn-link {
            font-size: 14px;
            color: #19860f;
            text-decoration: none;
            padding: 0;
        }

        .card-body .btn-link:hover {
            text-decoration: underline;
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

        /* Additional styles for reports & analytics */
        .report-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .report-section h4 {
            color: #19860f;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .filter-controls {
            background-color: #f0f2f5;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
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
                <a href="municipal-announcements.php" class="nav-link">
                    <i class="fas fa-bullhorn"></i> Announcements
                </a>
            </li>
            <li class="nav-item">
                <a href="municipal-reports_analytics.php" class="nav-link active">
                    <i class="fas fa-chart-line"></i> Reports & Analytics
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
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <button class="logout-btn" onclick="location.href='municipal-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container-fluid">
            <h1 class="page-title">Reports & Analytics</h1>

            <div class="filter-controls row g-3 align-items-end mb-4">
                <div class="col-md-3">
                    <label for="reportType" class="form-label">Report Type</label>
                    <select class="form-select" id="reportType">
                        <option value="crop">Crop Performance</option>
                        <option value="subsidy">Subsidy Distribution</option>
                        <option value="farmer">Farmer Demographics</option>
                        <option value="disaster">Disaster Impact</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="barangayFilter" class="form-label">Barangay</label>
                    <select class="form-select" id="barangayFilter">
                        <option value="">All Barangays</option>
                        <option value="brgyA">Brgy. San Jose</option>
                        <option value="brgyB">Brgy. Malanday</option>
                        <option value="brgyC">Brgy. Poblacion</option>
                        <!-- Dynamic options from DB -->
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="periodFilter" class="form-label">Period</label>
                    <select class="form-select" id="periodFilter">
                        <option value="current">Current Season</option>
                        <option value="last3m">Last 3 Months</option>
                        <option value="last6m">Last 6 Months</option>
                        <option value="yearly">Yearly</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-theme w-100"><i class="fas fa-filter me-2"></i>Apply Filters</button>
                </div>
            </div>

            <div class="row">
                <!-- Crop Performance Section -->
                <div class="col-lg-6">
                    <div class="report-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4>Crop Performance by Type</h4>
                            <button class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i> Download</button>
                        </div>
                        <div class="chart-container">
                            <canvas id="cropYieldChart"></canvas>
                        </div>
                        <p class="text-muted mt-3 mb-0" style="font-size: 0.9rem;">
                            Overall yield performance of major crops in the municipality.
                        </p>
                    </div>
                </div>

                <!-- Subsidy Distribution Section -->
                <div class="col-lg-6">
                    <div class="report-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4>Subsidy Distribution Status</h4>
                            <button class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i> Download</button>
                        </div>
                        <div class="chart-container">
                            <canvas id="subsidyStatusChart"></canvas>
                        </div>
                        <p class="text-muted mt-3 mb-0" style="font-size: 0.9rem;">
                            Breakdown of subsidy requests by status (Pending, Approved, Claimed).
                        </p>
                    </div>
                </div>

                <!-- Farmer Demographics Section -->
                <div class="col-lg-12">
                    <div class="report-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4>Farmer Demographics & Registration Trend</h4>
                            <button class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i> Download</button>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="chart-container">
                                    <canvas id="farmerAgeChart"></canvas>
                                </div>
                                <p class="text-center text-muted mt-2" style="font-size: 0.9rem;">Farmer Age Distribution</p>
                            </div>
                            <div class="col-md-6">
                                <div class="chart-container">
                                    <canvas id="farmerRegistrationTrend"></canvas>
                                </div>
                                <p class="text-center text-muted mt-2" style="font-size: 0.9rem;">New Farmer Registrations (Last 12 Months)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Disaster Impact Reports (Placeholder) -->
                <div class="col-lg-12">
                    <div class="report-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4>Disaster Impact Summary</h4>
                            <button class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i> Download</button>
                        </div>
                        <p class="text-muted">No major disaster reports available for the selected period.</p>
                        <div class="alert alert-info" role="alert" style="font-size: 0.9rem;">
                            This section would display aggregated data on crop damages, affected farmers, and estimated losses during disaster events, pulling from farmer-submitted reports.
                        </div>
                        <button class="btn btn-outline-primary btn-sm mt-2">View Detailed Disaster Reports</button>
                    </div>
                </div>

                <!-- Other Reports and Data Tables -->
                <div class="col-lg-12">
                    <div class="report-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4>Other Key Metrics</h4>
                            <button class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i> Download All Data</button>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Total Active Farmers</h6>
                                        <h2 class="card-text text-success">1,250</h2>
                                        <a href="municipal-farmer_profiles.php" class="btn-link">View all farmers</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Total Hectares Planted (Current Season)</h6>
                                        <h2 class="card-text text-primary">5,200</h2>
                                        <a href="municipal-crop_monitoring.php" class="btn-link">Monitor crops</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Pending Subsidy Requests</h6>
                                        <h2 class="card-text text-warning">85</h2>
                                        <a href="municipal-subsidy_management.php" class="btn-link">Review requests</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart.js Initialization -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sample data for charts (would be dynamically fetched from PHP/database)
            const cropYieldData = {
                labels: ['Rice', 'Corn', 'Vegetables', 'Fruits'],
                datasets: [{
                    label: 'Average Yield (tons/hectare)',
                    data: [4.5, 3.2, 1.8, 2.1],
                    backgroundColor: [
                        'rgba(25, 134, 15, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderColor: [
                        'rgba(25, 134, 15, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            };

            const subsidyStatusData = {
                labels: ['Approved & Claimed', 'Approved (Pending Claim)', 'Pending Review', 'Rejected'],
                datasets: [{
                    label: '# of Subsidies',
                    data: [350, 120, 85, 15],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)', // Green for Approved
                        'rgba(255, 193, 7, 0.7)', // Yellow for Approved Pending Claim
                        'rgba(23, 162, 184, 0.7)', // Cyan for Pending Review
                        'rgba(220, 53, 69, 0.7)' // Red for Rejected
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(23, 162, 184, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            };

            const farmerAgeData = {
                labels: ['18-25', '26-35', '36-45', '46-55', '56-65', '65+'],
                datasets: [{
                    label: 'Number of Farmers',
                    data: [150, 280, 400, 320, 180, 70],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(201, 203, 207, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(255, 205, 86, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(201, 203, 207, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(255, 205, 86, 1)'
                    ],
                    borderWidth: 1
                }]
            };

            const farmerRegistrationTrendData = {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'New Registrations',
                    data: [10, 15, 25, 20, 30, 18, 22, 28, 35, 40, 30, 25],
                    fill: false,
                    borderColor: '#19860f',
                    tension: 0.1
                }]
            };


            // Crop Yield Chart
            const cropYieldCtx = document.getElementById('cropYieldChart').getContext('2d');
            new Chart(cropYieldCtx, {
                type: 'bar',
                data: cropYieldData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: false, // Title moved to h4 tag
                        },
                        legend: {
                            display: false // No need for legend in single dataset bar chart
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Yield (tons/hectare)'
                            }
                        }
                    }
                }
            });

            // Subsidy Status Chart (Doughnut)
            const subsidyStatusCtx = document.getElementById('subsidyStatusChart').getContext('2d');
            new Chart(subsidyStatusCtx, {
                type: 'doughnut',
                data: subsidyStatusData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: false,
                        }
                    }
                }
            });

            // Farmer Age Distribution Chart
            const farmerAgeCtx = document.getElementById('farmerAgeChart').getContext('2d');
            new Chart(farmerAgeCtx, {
                type: 'pie', // Using pie for age distribution
                data: farmerAgeData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: false,
                        }
                    }
                }
            });

            // Farmer Registration Trend Chart
            const farmerRegistrationTrendCtx = document.getElementById('farmerRegistrationTrend').getContext('2d');
            new Chart(farmerRegistrationTrendCtx, {
                type: 'line',
                data: farmerRegistrationTrendData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: false,
                        },
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Registrations'
                            }
                        }
                    }
                }
            });

        });
    </script>
</body>

</html>