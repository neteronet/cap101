<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provincial Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/sidebars.css">
    <link rel="stylesheet" href="../css/style.css">

    <!-- FONT -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
</head>

<body class="background-color:#f8f9fa;">

    <header class="p-3 text-bg-primary sticky-top">
        <div class="container-fluid">
            <div class="row">
                <div
                    class="d-flex flex-wrap align-items-center justify-content-center justify-content-between col-md-9">
                    <a href="home.html" class="d-flex align-items-center mb-2 mb-lg-0 text-white text-decoration-none">
                        <img src="../photos/AntiqueProv Logo.png" alt="province of antique" height="50">
                    </a>
                </div>
                <div class="col-md-3 d-flex justify-content-end align-items-center">
                    <span class="text-white me-3">Hi, <strong>username</strong></span>
                    <button class="btn btn-outline-light btn-sm" onclick="location.href='logout.php'">Logout</button>
                </div>
            </div>
        </div>
    </header>

    <main class="row container-fluid">
        <div class="d-flex flex-column flex-shrink-0 p-3 bg-body-tertiary col-md-3"
            style="width: 280px; height: 100vh; overflow-y: auto;">
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="#" class="nav-link active" aria-current="page">
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="PrAgFarmerListPage.html" class="nav-link link-body-emphasis">
                        Farmers List
                    </a>
                </li>
                <li>
                    <a href="PrAGSubsidyManagement.html" class="nav-link link-body-emphasis">
                        Subsidy Management
                    </a>
                </li>
                <li>
                    <a href="#" class="nav-link link-body-emphasis">
                        Damage Reports
                    </a>
                </li>
                <li>
                    <a href="#" class="nav-link link-body-emphasis">
                        Reports & Analytics
                    </a>
                </li>
                <li>
                    <a href="#" class="nav-link link-body-emphasis">
                        Settings
                    </a>
                </li>
            </ul>
        </div>


        <div class="col-md-9 my-4" style="margin-left: 3rem;">
            <!-- Summary Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card-summary bg-white shadow-sm border-success">
                        <h6>Active Farmers</h6>
                        <h3 id="activeFarmers">0</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-summary bg-white shadow-sm border-warning">
                        <h6>Pending Subsidy Requests</h6>
                        <h3 id="pendingSubsidy">0</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-summary bg-white shadow-sm border-danger">
                        <h6>Damage Reports Awaiting Validation</h6>
                        <h3 id="damageReports">0</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-summary bg-white shadow-sm border-info">
                        <h6>Upcoming Distribution Events</h6>
                        <h3 id="distributionEvents">0</h3>
                    </div>
                </div>
            </div>


            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <a href="#" class="btn btn-outline-danger w-100 quick-action-btn">Validate Damage Reports</a>
                </div>
                <div class="col-md-4">
                    <a href="#" class="btn btn-outline-success w-100 quick-action-btn">Approve Subsidy Beneficiaries</a>
                </div>
                <div class="col-md-4">
                    <a href="#" class="btn btn-outline-primary w-100 quick-action-btn">Generate QR Codes</a>
                </div>
            </div>

            <!-- Charts for Tracking-->
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Subsidies by Municipality</h5>
                            <canvas id="subsidyChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Damages by Calamity Type</h5>
                            <canvas id="damageChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!--SCRIPTS BOOTSTRAP -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
        crossorigin="anonymous"></script>
</body>

</html>