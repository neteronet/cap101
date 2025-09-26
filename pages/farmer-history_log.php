<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Account - History Log</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom Styles -->
    <style>
        body {
            font-family: "Poppins", sans-serif;
            background: #f8f9fa;
        }

        header {
            background-color: #19860f; /* Green theme */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header-brand {
            display: flex;
            align-items: center;
            color: #fff;
            text-decoration: none;
        }

        .header-brand img {
            height: 40px; /* Adjusted logo size */
            margin-right: 10px;
        }

        .header-brand span {
            font-weight: 600;
            font-size: 1.25rem;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 64px; /* Adjust based on header height */
            left: 0;
            width: 250px; /* Slightly wider sidebar */
            height: calc(100vh - 64px);
            background: #fff;
            border-right: 1px solid rgba(0, 0, 0, 0.125);
            padding: 1rem 0.5rem;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
        }

        .sidebar .nav-link {
            font-weight: 500;
            color: #444;
            border-radius: 0.375rem;
            padding: 0.75rem 1rem;
            margin-bottom: 0.25rem;
            font-size: 1rem;
            transition: all 0.2s ease-in-out;
            display: flex;
            align-items: center;
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            font-size: 1.1rem;
        }

        .sidebar .nav-link.active {
            background-color: #19860f;
            color: #fff;
        }

        .sidebar .nav-link:hover:not(.active) {
            background-color: #e6f2e6; /* Lighter green for hover */
            color: #19860f;
        }

        .sidebar .nav-item .collapse .nav-link {
            padding-left: 2.5rem; /* Indent for submenu items */
            font-size: 0.95rem;
        }
        .sidebar .nav-item .collapse .nav-link.active {
            background-color: #157a0d; /* Darker green for active submenu */
        }
        .sidebar .nav-item .collapse .nav-link:hover:not(.active) {
            background-color: #d1ead1; /* Even lighter green for submenu hover */
        }

        /* Logout Button */
        .logout-btn {
            background: linear-gradient(135deg, #ff4b2b, #ff416c);
            color: #fff;
            border: none;
            padding: 8px 20px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .logout-btn:hover {
            background: linear-gradient(135deg, #ff416c, #ff4b2b);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }

        /* Themed Buttons */
        .btn-theme {
            background-color: #19860f;
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 0.375rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-theme:hover {
            background-color: #157a0d;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        main {
            margin-left: 250px; /* Match sidebar width */
            padding: 2rem;
            min-height: calc(100vh - 64px); /* Ensure main content takes full height */
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #28a745; /* Green accent for title */
            margin-bottom: 1.5rem;
        }

        .card {
            border-radius: 0.75rem; /* Slightly more rounded cards */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem; /* Spacing between cards */
        }

        .card-title {
            color: #19860f; /* Green title for cards */
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .log-entry {
            background: #f1fdf1;
            border-left: 5px solid #28a745; /* Stronger green accent */
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
        }
        .log-entry i {
            margin-right: 10px;
            color: #28a745;
            font-size: 1.2rem;
        }
        .log-entry strong {
            color: #333;
        }
        .log-entry em {
            color: #555;
            font-size: 0.9em;
        }
        .log-entry.warning {
            border-left-color: #ffc107;
            background-color: #fff3cd;
        }
        .log-entry.warning i {
            color: #ffc107;
        }
        .log-entry.danger {
            border-left-color: #dc3545;
            background-color: #f8d7da;
        }
        .log-entry.danger i {
            color: #dc3545;
        }
        .log-entry-date {
            font-size: 0.8em;
            color: #999;
            margin-left: auto;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="p-3 text-white sticky-top">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between">
                <a href="ProvincialAgriHome.html" class="header-brand">
                    <img src="../photos/AntiqueProv Logo.png" alt="Province of Antique" />
                    <span>AntiqueProv Agri</span>
                </a>
                <div class="d-flex align-items-center">
                    <span class="me-3">Hi, <strong>username</strong></span>
                    <button class="logout-btn" onclick="location.href='farmers-logout.php'">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <nav class="sidebar">
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
                <a href="farmer-subsidy_status.php" class="nav-link">
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
                <a href="#cropMonitoringSubmenu" data-bs-toggle="collapse" class="nav-link d-flex justify-content-between align-items-center active" aria-expanded="true">
                    <div><i class="fas fa-seedling"></i> Crop Monitoring</div>
                    <i class="fas fa-chevron-down fa-xs"></i>
                </a>
                <div class="collapse show" id="cropMonitoringSubmenu">
                    <ul class="nav flex-column ms-3">
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
                        <li class="nav-item">
                            <a href="farmer-photo_upload.php" class="nav-link">
                                <i class="fas fa-camera"></i> Photo Upload
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="farmer-history_log.php" class="nav-link active">
                                <i class="fas fa-history"></i> History Log
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
    </nav>

    <!-- Content -->
    <main>
        <h2 class="page-title"><i class="fas fa-history me-2"></i>History Log</h2>
        <p class="text-muted mb-4">A comprehensive record of your farm activities, crop updates, and important events.</p>

        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-clipboard-list me-2"></i>Activity Timeline</h5>
                        <p class="text-muted small">Filter by crop or activity type (functionality to be added).</p>

                        <div class="timeline">
                            <!-- Example Log Entries -->
                            <div class="log-entry">
                                <i class="fas fa-seedling"></i>
                                <div>
                                    <strong>Rice (Field 1)</strong> - Planting Status Updated: Seeds Planted 🌱 <br>
                                    <em>Photo uploaded for verification.</em>
                                </div>
                                <span class="log-entry-date">August 20, 2025</span>
                            </div>

                            <div class="log-entry warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <strong>Corn (Field 2)</strong> - Planting Status Updated: Not yet planted ❌ <br>
                                    <em>Delayed due to weather conditions.</em>
                                </div>
                                <span class="log-entry-date">July 10, 2025</span>
                            </div>

                            <div class="log-entry">
                                <i class="fas fa-check-circle"></i>
                                <div>
                                    <strong>Vegetables (Plot 3)</strong> - Harvest Recorded: Harvested 🥕 <br>
                                    <em>Yield: 150kg. Photo uploaded.</em>
                                </div>
                                <span class="log-entry-date">June 01, 2025</span>
                            </div>

                            <div class="log-entry">
                                <i class="fas fa-chart-line"></i>
                                <div>
                                    <strong>Rice (Field 1)</strong> - Progress Update: Flowering Stage (50%) 🌾<br>
                                    <em>Manual update submitted.</em>
                                </div>
                                <span class="log-entry-date">May 15, 2025</span>
                            </div>

                            <div class="log-entry">
                                <i class="fas fa-tint"></i>
                                <div>
                                    <strong>Corn (Field 2)</strong> - Activity Logged: Initial Fertilization 🧪<br>
                                    <em>Urea applied to 1 hectare.</em>
                                </div>
                                <span class="log-entry-date">April 25, 2025</span>
                            </div>

                            <div class="log-entry danger">
                                <i class="fas fa-bug"></i>
                                <div>
                                    <strong>Vegetables (Plot 3)</strong> - Incident Reported: Minor Pest Infestation 🐛<br>
                                    <em>Photo uploaded. Action: organic pesticide applied.</em>
                                </div>
                                <span class="log-entry-date">April 05, 2025</span>
                            </div>

                            <div class="log-entry">
                                <i class="fas fa-camera"></i>
                                <div>
                                    <strong>Rice (Field 1)</strong> - Photo Uploaded: Growth Progress (30 days) 📸<br>
                                    <em>Verification status: Approved.</em>
                                </div>
                                <span class="log-entry-date">March 28, 2025</span>
                            </div>

                            <div class="log-entry">
                                <i class="fas fa-hand-holding-usd"></i>
                                <div>
                                    <strong>Subsidy Claimed:</strong> Fertilizer Assistance 💰<br>
                                    <em>Claim ID: #ASST-2025-001.</em>
                                </div>
                                <span class="log-entry-date">March 10, 2025</span>
                            </div>

                            <div class="log-entry">
                                <i class="fas fa-user-circle"></i>
                                <div>
                                    <strong>Profile Updated:</strong> Contact Information 📝<br>
                                    <em>New mobile number: 09XX-XXX-XXXX.</em>
                                </div>
                                <span class="log-entry-date">February 01, 2025</span>
                            </div>

                            <!-- Pagination/Load More could go here -->
                            <div class="text-center mt-4">
                                <button class="btn btn-outline-secondary"><i class="fas fa-redo me-1"></i>Load More Entries</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>