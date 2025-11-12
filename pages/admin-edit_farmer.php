<?php
session_start();
include '../includes/connection.php'; // Ensure this path is correct for your setup

// --- IMPROVEMENT 1: Robust Connection Check to prevent crashing on DB failure ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? "Connection object not set"));
    header("location: database_error.php"); 
    exit();
}

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: admin-login.php");
    exit();
}

$admin_user_id = $_SESSION['user_id'];
$display_name = 'Admin'; // Default fallback
$farmer_data = null;
$is_admin = false; // Flag for explicit admin check
$message = '';
$message_type = '';
$farmer_id = $_GET['farmer_id'] ?? null;

// Check for messages from redirects
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}

// --- IMPROVEMENT 2: Fetch admin's name AND user type for security check ---
$stmt_admin_name = $conn->prepare("SELECT name, user_type FROM users WHERE user_id = ?");
if ($stmt_admin_name) {
    $stmt_admin_name->bind_param("i", $admin_user_id);
    $stmt_admin_name->execute();
    $stmt_admin_name->bind_result($db_name, $db_user_type);
    $stmt_admin_name->fetch();
    $stmt_admin_name->close();

    if ($db_name) {
        $display_name = htmlspecialchars($db_name);
    }
    
    // Explicit Admin Authorization Check
    if ($db_user_type === 'admin') {
        $is_admin = true;
    } 
} else {
    error_log("Failed to prepare statement for admin name/type: " . $conn->error);
}

// --- IMPROVEMENT 3: Enforce Admin Access (Prevents MAO/Farmer access) ---
if (!$is_admin) {
    session_unset();
    session_destroy();
    header("location: admin-login.php");
    exit();
}


// --- HANDLE FORM SUBMISSION (UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_farmer_submit'])) {
    $update_farmer_id = $_POST['farmer_id'] ?? null;
    
    // Input validation and sanitization
    $rsbsa_id       = trim($_POST['rsbsa_id'] ?? '');
    $first_name     = trim($_POST['first_name'] ?? '');
    $middle_name    = trim($_POST['middle_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $land_location  = trim($_POST['land_location'] ?? '');
    $land_size      = trim($_POST['land_size'] ?? '');
    $age            = filter_var($_POST['age'] ?? null, FILTER_VALIDATE_INT); // Validate age as integer
    $gender         = $_POST['gender'] ?? '';
    $civil_status   = $_POST['civil_status'] ?? '';
    $crop           = trim($_POST['crop'] ?? '');

    // Basic required field validation
    if (empty($rsbsa_id) || empty($first_name) || empty($last_name) || empty($address) || empty($contact_number) || empty($land_location) || empty($land_size) || $age === false || empty($gender) || empty($civil_status) || empty($crop)) {
        $message = "All required fields must be filled and Age must be a valid number.";
        $message_type = 'danger';
    } elseif ($update_farmer_id && is_numeric($update_farmer_id)) {
        // Prepare land_details as JSON
        $land_details_array = [
            'location' => $land_location,
            'size' => $land_size
        ];
        $land_details_json = json_encode($land_details_array);

        // --- IMPROVEMENT 4: Update Statement for Farmer Details ---
        $stmt_update = $conn->prepare("
            UPDATE farmers 
            SET 
                rsbsa_id = ?, 
                first_name = ?, 
                middle_name = ?, 
                last_name = ?, 
                address = ?, 
                contact_number = ?, 
                land_details = ?, 
                age = ?, 
                gender = ?, 
                civil_status = ?, 
                crop = ?
            WHERE 
                farmer_id = ?
        ");

        if ($stmt_update) {
            // Check if $age is not null before binding 'i'
            $age_to_bind = is_int($age) ? $age : null; 

            // Note: age is 'i' (integer), others are 's' (string)
            $stmt_update->bind_param(
                "sssssssisssi",
                $rsbsa_id,
                $first_name,
                $middle_name,
                $last_name,
                $address,
                $contact_number,
                $land_details_json,
                $age_to_bind, // Use validated/filtered age
                $gender,
                $civil_status,
                $crop,
                $update_farmer_id
            );

            if ($stmt_update->execute()) {
                $message = "Farmer details updated successfully for ID #{$update_farmer_id}!";
                $message_type = 'success';
            } else {
                $message = "Error updating farmer details: " . $stmt_update->error;
                $message_type = 'danger';
                error_log("Error updating farmer details: " . $stmt_update->error);
            }
            $stmt_update->close();
        } else {
            $message = "Database error: Could not prepare update statement.";
            $message_type = 'danger';
            error_log("Failed to prepare update statement: " . $conn->error);
        }
    } else {
        $message = "Invalid Farmer ID for update.";
        $message_type = 'danger';
    }

    // --- IMPROVEMENT 5: PRG Redirect on Post ---
    // Use session for message storage to handle the PRG pattern correctly
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
    
    // Redirect back to the view page with a success/failure message
    header("Location: admin-view_farmers.php");
    exit();
}

// --- FETCH FARMER DATA FOR EDITING (GET Request) ---
if ($farmer_id && is_numeric($farmer_id)) {
    // Note: No need for a WHERE user_type = 'farmer' here, as farmers are already separated 
    // from 'users' in the JOIN. Admin can access all farmer data.
    $stmt_fetch = $conn->prepare("
        SELECT 
            f.*, u.name AS user_full_name, u.username, u.user_type -- Fetch user_type for verification (extra check)
        FROM 
            farmers f 
        JOIN 
            users u ON f.user_id = u.user_id 
        WHERE 
            f.farmer_id = ? AND u.user_type = 'farmer' -- IMPROVEMENT 6: Ensure it is a 'farmer' type user
    ");
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $farmer_id);
        $stmt_fetch->execute();
        $result = $stmt_fetch->get_result();
        $farmer_data = $result->fetch_assoc();
        $stmt_fetch->close();

        if ($farmer_data) {
            // Decode land_details JSON for form pre-filling
            $land_details = json_decode($farmer_data['land_details'], true);
            $farmer_data['land_location'] = htmlspecialchars($land_details['location'] ?? '');
            $farmer_data['land_size'] = htmlspecialchars($land_details['size'] ?? '');
        } else {
            $message = "Farmer details not found or user is not a 'farmer' type.";
            $message_type = 'danger';
            $farmer_id = null; // Invalidate ID if data not found
        }
    } else {
        $message = "Database error fetching farmer: " . $conn->error;
        $message_type = 'danger';
        error_log("Failed to prepare fetch statement: " . $conn->error);
    }
} else if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $message = "Invalid or missing Farmer ID.";
    $message_type = 'danger';
}

// --- IMPROVEMENT 7: Handle PRG Session Messages for GET request ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Close the connection as the very last step
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin - Edit Farmer</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Custom Styles (reusing styles for consistency) -->
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
            max-width: 900px;
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
    </style>
</head>

<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <a href="admin-dashboard.php" class="header-brand">
            <img src="../photos/Department_of_Agriculture_of_the_Philippines.png" alt="Province of Antique" />
            <div>Province of Antique</div>
        </a>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="admin-dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-add_user.php" class="nav-link">
                    <i class="fas fa-user-plus"></i> Add User
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-view_farmers.php" class="nav-link active">
                    <i class="fas fa-users"></i> View Farmers
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-register_farmer.php" class="nav-link">
                    <i class="fas fa-clipboard-list"></i> Register Farmer Details
                </a>
            </li>
        </ul>
    </nav>

    <!-- Header -->
    <div class="card-header card-header-custom d-flex justify-content-end align-items-center">
        <span class="me-3">Hi, <strong><?php echo htmlspecialchars($display_name); ?></strong></span>
        <button class="logout-btn" onclick="location.href='admin-logout.php'">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h1 class="page-title">Edit Farmer Details</h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($farmer_data): ?>
                <div class="card">
                    <div class="card-header">
                        Editing Details for: <strong><?php echo htmlspecialchars($farmer_data['user_full_name']); ?></strong> (User ID: <?php echo $farmer_data['user_id']; ?>)
                    </div>
                    <div class="card-body">
                        <form action="admin-edit_farmer.php" method="POST">
                            <input type="hidden" name="farmer_id" value="<?php echo htmlspecialchars($farmer_data['farmer_id']); ?>">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="rsbsa_id" class="form-label">RSBSA ID <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="rsbsa_id" name="rsbsa_id" value="<?php echo htmlspecialchars($farmer_data['rsbsa_id']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($farmer_data['first_name']); ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($farmer_data['middle_name']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($farmer_data['last_name']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="address" name="address" rows="2" required><?php echo htmlspecialchars($farmer_data['address']); ?></textarea>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($farmer_data['contact_number']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="age" class="form-label">Age <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="age" name="age" value="<?php echo htmlspecialchars($farmer_data['age']); ?>" required min="18">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php if ($farmer_data['gender'] == 'Male') echo 'selected'; ?>>Male</option>
                                        <option value="Female" <?php if ($farmer_data['gender'] == 'Female') echo 'selected'; ?>>Female</option>
                                        <option value="Other" <?php if ($farmer_data['gender'] == 'Other') echo 'selected'; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="civil_status" class="form-label">Civil Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="civil_status" name="civil_status" required>
                                        <option value="">Select Civil Status</option>
                                        <option value="Single" <?php if ($farmer_data['civil_status'] == 'Single') echo 'selected'; ?>>Single</option>
                                        <option value="Married" <?php if ($farmer_data['civil_status'] == 'Married') echo 'selected'; ?>>Married</option>
                                        <option value="Widowed" <?php if ($farmer_data['civil_status'] == 'Widowed') echo 'selected'; ?>>Widowed</option>
                                        <option value="Divorced" <?php if ($farmer_data['civil_status'] == 'Divorced') echo 'selected'; ?>>Divorced</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="land_location" class="form-label">Land Location <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="land_location" name="land_location" value="<?php echo htmlspecialchars($farmer_data['land_location']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="land_size" class="form-label">Land Size (e.g., "1.5 hectares") <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="land_size" name="land_size" value="<?php echo htmlspecialchars($farmer_data['land_size']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="crop" class="form-label">Main Crop <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="crop" name="crop" value="<?php echo htmlspecialchars($farmer_data['crop']); ?>" required>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <a href="admin-view_farmers.php" class="btn btn-secondary me-2">Cancel</a>
                                <button type="submit" name="edit_farmer_submit" class="btn btn-warning">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger" role="alert">
                    <p>Unable to load farmer details. Please go back to the <a href="admin-view_farmers.php">View Farmers</a> page.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>