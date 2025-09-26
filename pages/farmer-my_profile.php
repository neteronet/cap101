<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Profile - Farmer Portal</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

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
            display: flex;
            align-items: center;
            text-decoration: none;
            box-sizing: border-box; /* Ensure padding doesn't push it out */
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
            padding: 5px;
            background: #19860f;
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
            justify-content: flex-end; /* Align to the right */
            z-index: 1060;
            border-bottom: 1px solid #ddd;
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

        main {
            margin-left: 250px;
            padding: 72px 2rem 2rem 2rem;
            background: #f8f9fa;
            min-height: 100vh;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #19860f;
            margin-bottom: 1rem; /* Reduced margin to match dashboard */
        }

        /* General card styling for consistency */
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem; /* Consistent bottom margin */
            border: 1px solid #ddd; /* Add border for consistency */
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }

        .profile-header img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #19860f;
        }

        .profile-header h4 {
            font-size: 1.5rem; /* Consistent with dashboard card titles */
            color: #19860f;
            margin-bottom: 0.3rem;
        }

        .info-label {
            font-weight: 600;
            margin-right: 0.5rem;
            color: #555; /* Slightly darker for better readability */
        }

        .section-title {
            font-size: 1.25rem; /* Consistent with dashboard card titles */
            font-weight: 600;
            color: #19860f;
            margin-top: 1.5rem; /* Adjusted for consistency */
            margin-bottom: 1rem;
        }

        /* Adjusting text within cards for consistency */
        .card-body p {
            margin-bottom: 0.5rem; /* Consistent spacing for paragraphs */
            font-size: 15px; /* Slightly smaller for detailed info */
        }
        .card-body .mb-0 { /* For the last paragraph in a section */
            margin-bottom: 0;
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
            <li class="nav-item"><a href="farmer-dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="farmer-my_profile.php" class="nav-link active"><i class="fas fa-user-circle"></i> My Profile</a></li>
            <li class="nav-item"><a href="farmer-subsidy_status.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i> Subsidy Status</a></li>
            <li class="nav-item"><a href="farmer-announcement.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li class="nav-item"><a href="farmer-apply_for_assistance.php" class="nav-link"><i class="fas fa-file-invoice"></i> Apply for Assistance</a></li>
            <li class="nav-item">
                <a href="#cropMonitoringSubmenu" data-bs-toggle="collapse" class="nav-link d-flex justify-content-between align-items-center">
                    <div><i class="fas fa-seedling"></i> Crop Monitoring</div>
                    <i class="fas fa-chevron-down fa-xs"></i>
                </a>
                <div class="collapse" id="cropMonitoringSubmenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item"><a href="farmer-planting_status.php" class="nav-link"><i class="fas fa-leaf"></i> Planting Status</a></li>
                        <li class="nav-item"><a href="farmer-progress_tracking.php" class="nav-link"><i class="fas fa-chart-line"></i> Progress Tracking</a></li>
                    </ul>
                </div>
            </li>
        </ul>
    </nav>

    <!-- Header -->
    <div class="card-header card-header-custom">
        <span class="me-3">Hi, <strong>username</strong></span>
        <button class="logout-btn" onclick="location.href='farmers-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h1 class="page-title">My Profile</h1>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="profile-header">
                        <img src="../photos/farmer-avatar.png" alt="Farmer Photo">
                        <div>
                            <h4>Juan Dela Cruz</h4>
                            <p class="mb-1 text-muted small">RSBSA ID: <strong>RSBSA-123456</strong></p>
                            <p class="mb-0 text-muted small">Barangay: Maybato Norte</p>
                        </div>
                    </div>

                    <h5 class="section-title mb-3"><i class="fas fa-info-circle me-2"></i>Personal Information</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p><span class="info-label"><i class="fas fa-calendar-alt me-2 text-success"></i>Age:</span> 45</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p><span class="info-label"><i class="fas fa-venus-mars me-2 text-success"></i>Gender:</span> Male</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p><span class="info-label"><i class="fas fa-phone-alt me-2 text-success"></i>Contact:</span> 09123456789</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p><span class="info-label"><i class="fas fa-ring me-2 text-success"></i>Civil Status:</span> Married</p>
                        </div>
                    </div>
                </div>
            </div>

            <h5 class="section-title"><i class="fas fa-map-marked-alt me-2"></i>Land Details</h5>

            <div class="card">
                <div class="card-body">
                    <p><strong>Location:</strong> Barangay Maybato Sur</p>
                    <p><strong>Area:</strong> 2 hectares</p>
                    <p class="mb-0"><strong>Crop:</strong> Palay (Rice)</p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <p><strong>Location:</strong> Barangay San Jose</p>
                    <p><strong>Area:</strong> 1.5 hectares</p>
                    <p class="mb-0"><strong>Crop:</strong> Corn</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>