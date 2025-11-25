<?php
session_start(); // Start the session at the very beginning of the script

include '../includes/connection.php'; // Ensure your connection file is correctly included

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

// --- Fetch Claim History for Approved and Claimed Assistance ---
$claim_history = [];
$stmt_claims = $conn->prepare("
    SELECT sc.claim_id, aa.assistance_type, aa.seed_type, aa.seed_quantity, aa.engine_type, sc.claim_date, sc.notes
    FROM subsidy_claims sc
    JOIN assistance_applications aa ON sc.application_id = aa.application_id
    WHERE sc.user_id = ? AND aa.status = 'Approved' AND aa.claimed = 1
    ORDER BY sc.claim_date DESC
");
if ($stmt_claims) {
    $stmt_claims->bind_param("i", $user_id);
    $stmt_claims->execute();
    $stmt_claims->bind_result($claim_id, $assistance_type, $seed_type, $seed_quantity, $engine_type, $claim_date, $notes);
    while ($stmt_claims->fetch()) {
        $claim_history[] = [
            'claim_id' => $claim_id,
            'assistance_type' => $assistance_type,
            'seed_type' => $seed_type,
            'seed_quantity' => $seed_quantity,
            'engine_type' => $engine_type,
            'claim_date' => $claim_date,
            'notes' => $notes
        ];
    }
    $stmt_claims->close();
} else {
    error_log("Failed to prepare claim history statement: " . $conn->error);
}

// --- Fetch Application History ---
$application_history = [];
$stmt_apps = $conn->prepare("
    SELECT application_id, assistance_type, seed_type, seed_quantity, engine_type, status, application_date, remarks
    FROM assistance_applications
    WHERE user_id = ?
    ORDER BY application_date DESC
");
if ($stmt_apps) {
    $stmt_apps->bind_param("i", $user_id);
    $stmt_apps->execute();
    $stmt_apps->bind_result($app_id, $app_assistance_type, $app_seed_type, $app_seed_quantity, $app_engine_type, $app_status, $app_date, $app_remarks);
    while ($stmt_apps->fetch()) {
        $application_history[] = [
            'application_id' => $app_id,
            'assistance_type' => $app_assistance_type,
            'seed_type' => $app_seed_type,
            'seed_quantity' => $app_seed_quantity,
            'engine_type' => $app_engine_type,
            'status' => $app_status,
            'application_date' => $app_date,
            'remarks' => $app_remarks
        ];
    }
    $stmt_apps->close();
} else {
    error_log("Failed to prepare application history statement: " . $conn->error);
}

$conn->close(); // Close the connection after all database operations

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Farmer Account - Claim History</title>

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

        .table th {
            background-color: #19860f;
            color: #fff;
        }

        .table td {
            vertical-align: middle;
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
            <li class="nav-item"><a href="farmer-apply_for_assistance.php" class="nav-link"><i class="fas fa-hand-holding-usd"></i>Apply for Assistance</a></li>
            <li class="nav-item"><a href="farmer-planting_status.php" class="nav-link"><i class="fas fa-leaf"></i> Planting Status</a></li>
            <li class="nav-item"><a href="farmer-claim_history.php" class="nav-link active"><i class="fas fa-history"></i> Claim History</a></li>
            <!-- Removed link to Progress Tracking -->
            <li class="nav-item"><a href="farmer-announcement.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li class="nav-item"><a href="farmer-my_profile.php" class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a></li>
        </ul>


    </nav>

    <!-- Header -->
    <div class="card-header card-header-custom d-flex justify-content-end align-items-center">
        <!-- Changed "username" to "name" in the greeting -->
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <button class="logout-btn" onclick="location.href='farmers-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">

            <h1 class="page-title"><i class="fas fa-history me-2"></i>Claim History</h1>
            <p class="text-muted mb-4">
                View the history of your approved and claimed assistance applications.
            </p>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-list me-2"></i>Approved Claims</h5>
                    <?php if (!empty($claim_history)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Claim ID</th>
                                        <th>Assistance Type</th>
                                        <th>Details</th>
                                        <th>Claim Date</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($claim_history as $claim): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($claim['claim_id']); ?></td>
                                            <td><?php echo htmlspecialchars($claim['assistance_type']); ?></td>
                                            <td>
                                                <?php
                                                $details = [];
                                                if ($claim['seed_type']) $details[] = "Seed: " . htmlspecialchars($claim['seed_type']);
                                                if ($claim['seed_quantity']) $details[] = "Qty: " . htmlspecialchars($claim['seed_quantity']);
                                                if ($claim['engine_type']) $details[] = "Engine: " . htmlspecialchars($claim['engine_type']);
                                                echo implode(", ", $details);
                                                ?>
                                            </td>
                                            <td><?php echo date('F j, Y', strtotime($claim['claim_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($claim['notes'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No approved claims found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-file-alt me-2"></i>Application History</h5>
                    <?php if (!empty($application_history)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Application ID</th>
                                        <th>Assistance Type</th>
                                        <th>Details</th>
                                        <th>Status</th>
                                        <th>Application Date</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($application_history as $app): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($app['application_id']); ?></td>
                                            <td><?php echo htmlspecialchars($app['assistance_type']); ?></td>
                                            <td>
                                                <?php
                                                $details = [];
                                                if ($app['seed_type']) $details[] = "Seed: " . htmlspecialchars($app['seed_type']);
                                                if ($app['seed_quantity']) $details[] = "Qty: " . htmlspecialchars($app['seed_quantity']);
                                                if ($app['engine_type']) $details[] = "Engine: " . htmlspecialchars($app['engine_type']);
                                                echo implode(", ", $details);
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status = htmlspecialchars($app['status']);
                                                $badge_class = 'badge ';
                                                if ($status === 'Approved') {
                                                    $badge_class .= 'bg-success';
                                                } elseif ($status === 'Pending') {
                                                    $badge_class .= 'bg-warning';
                                                } elseif ($status === 'Rejected') {
                                                    $badge_class .= 'bg-danger';
                                                } else {
                                                    $badge_class .= 'bg-secondary';
                                                }
                                                echo "<span class=\"$badge_class\">$status</span>";
                                                ?>
                                            </td>
                                            <td><?php echo date('F j, Y', strtotime($app['application_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($app['remarks'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No applications found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
