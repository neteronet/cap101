<?php
session_start();
include '../includes/connection.php'; // Ensure this path is correct for your setup

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: admin-login.php");
    exit();
}

$admin_user_id = $_SESSION['user_id'];
$display_name = 'Admin'; // Default fallback
$message = '';
$message_type = '';

// Fetch admin's name
$stmt_admin_name = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
if ($stmt_admin_name) {
    $stmt_admin_name->bind_param("i", $admin_user_id);
    $stmt_admin_name->execute();
    $stmt_admin_name->bind_result($db_name);
    $stmt_admin_name->fetch();
    if ($db_name) {
        $display_name = htmlspecialchars($db_name);
    }
    $stmt_admin_name->close();
} else {
    error_log("Failed to prepare statement for admin name: " . $conn->error);
}

// Fetch all *unregistered* farmers (users with user_type='farmer' but no entry in 'farmers' table)
$unregistered_users = [];
$stmt_users = $conn->prepare("
    SELECT 
        u.user_id, 
        u.name, 
        u.username 
    FROM 
        users u
    LEFT JOIN 
        farmers f ON u.user_id = f.user_id
    WHERE 
        u.user_type = 'farmer' AND f.farmer_id IS NULL
    ORDER BY 
        u.name ASC
");

if ($stmt_users) {
    $stmt_users->execute();
    $result = $stmt_users->get_result();
    while ($row = $result->fetch_assoc()) {
        $unregistered_users[] = $row;
    }
    $stmt_users->close();
} else {
    error_log("Failed to prepare statement for fetching unregistered users: " . $conn->error);
}


// --- HANDLE FORM SUBMISSION (REGISTRATION) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_farmer_submit_page'])) {
    $register_user_id = $_POST['register_user_id'] ?? null;
    $rsbsa_id = $_POST['rsbsa_id'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $address = $_POST['address'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $land_location = $_POST['land_location'] ?? '';
    $land_size = $_POST['land_size'] ?? '';
    $age = $_POST['age'] ?? null;
    $gender = $_POST['gender'] ?? '';
    $civil_status = $_POST['civil_status'] ?? '';
    $crop = $_POST['crop'] ?? '';

    // Sanitize age to ensure it is integer or null (good practice)
    $age = filter_var($age, FILTER_VALIDATE_INT) !== false ? (int)$age : null;


    if ($register_user_id && is_numeric($register_user_id)) {
        // Prepare land_details as JSON
        $land_details_array = [
            'location' => $land_location,
            'size' => $land_size
        ];
        $land_details_json = json_encode($land_details_array);

        // Check if farmer is already registered (Crucial check)
        $stmt_check = $conn->prepare("SELECT farmer_id FROM farmers WHERE user_id = ?");
        $stmt_check->bind_param("i", $register_user_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $message = "Farmer for this user ID is already registered.";
            $message_type = 'warning';
        } else {
            // The INSERT statement does not include the new 'qr_code_image' column yet.
            // It will be updated *after* insertion to get the auto-generated farmer_id.
            $stmt_insert = $conn->prepare("INSERT INTO farmers (user_id, rsbsa_id, first_name, middle_name, last_name, address, contact_number, land_details, age, gender, civil_status, crop) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            if ($stmt_insert) {
                $stmt_insert->bind_param(
                    "isssssssisss",
                    $register_user_id,
                    $rsbsa_id,
                    $first_name,
                    $middle_name,
                    $last_name,
                    $address,
                    $contact_number,
                    $land_details_json,
                    $age,
                    $gender,
                    $civil_status,
                    $crop
                );

                if ($stmt_insert->execute()) {
                    // --- MODIFIED CODE FOR QR GENERATION AND DATABASE UPDATE ---
                    
                    // 1. Get the ID of the newly inserted row
                    $new_farmer_id = $conn->insert_id;

                    // 2. Define the data to be encoded (e.g., the farmer_id for quick lookup)
                    $qr_code_data = (string)$new_farmer_id; 
                    
                    // 3. Generate QR Code Image URL using an API (e.g., Google Charts API)
                    // Format: https://chart.googleapis.com/chart?chs=<size>x<size>&cht=qr&chl=<data>
                    $qr_code_size = '200x200'; // Define size
                    $qr_code_url = "https://chart.googleapis.com/chart?chs=$qr_code_size&cht=qr&chl=" . urlencode($qr_code_data);

                    // 4. Update the 'farmers' table with the generated QR code URL
                    // NOTE: This requires the SQL query to add the 'qr_code_image' column to be run first.
                    $stmt_update_qr = $conn->prepare("UPDATE farmers SET qr_code_image = ? WHERE farmer_id = ?");
                    if ($stmt_update_qr) {
                        $stmt_update_qr->bind_param("si", $qr_code_url, $new_farmer_id);
                        if (!$stmt_update_qr->execute()) {
                            // Log error but allow operation to continue as insertion was successful
                            error_log("Failed to update QR code image for farmer ID $new_farmer_id: " . $stmt_update_qr->error);
                        }
                        $stmt_update_qr->close();
                    } else {
                        error_log("Failed to prepare statement for QR code update: " . $conn->error);
                    }

                    // 5. Redirect to the display page
                    $success_message = "Farmer details registered successfully! The QR Code has been generated and saved. Please print it.";
                    $message_type = 'success';
                    
                    // Pass the new farmer_id and the success message
                    header("Location: admin-show_farmer_qr.php?farmer_id=" . $new_farmer_id . "&msg=" . urlencode($success_message) . "&type=" . urlencode($message_type));
                    exit();
                    // --- END MODIFIED CODE ---
                } else {
                    $message = "Error registering farmer details: " . $stmt_insert->error;
                    $message_type = 'danger';
                    error_log("Error inserting farmer details: " . $stmt_insert->error);
                }
                $stmt_insert->close();
            } else {
                $message = "Database error: Could not prepare farmer registration statement.";
                $message_type = 'danger';
                error_log("Failed to prepare farmer registration statement: " . $conn->error);
            }
        }
        $stmt_check->close();
    } else {
        $message = "Invalid User ID provided for registration.";
        $message_type = 'danger';
    }
    // Redirect to self with message to show success/error (This handles non-success cases like 'already registered' or 'invalid user id')
    header("Location: admin-register_farmer.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit();
}

// Check for messages from redirects (if not a POST submission)
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}

// Ensure $conn is closed only if it was opened and not already closed
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin - Register Farmer Details</title>
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
                <a href="admin-view_farmers.php" class="nav-link">
                    <i class="fas fa-users"></i> View Farmers
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-register_farmer.php" class="nav-link active">
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
            <h1 class="page-title">Register New Farmer Details</h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form action="admin-register_farmer.php" method="POST">
                        <h5 class="card-title mb-4">Farmer Personal and Land Information</h5>

                        <div class="mb-3">
                            <label for="register_user_id" class="form-label">Select Farmer User (Unregistered) <span class="text-danger">*</span></label>
                            <select class="form-select" id="register_user_id" name="register_user_id" required>
                                <option value="">-- Select Farmer User --</option>
                                <?php if (!empty($unregistered_users)): ?>
                                    <?php foreach ($unregistered_users as $user): ?>
                                        <option value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                            <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>All registered farmers already have details.</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <hr class="my-4">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="rsbsa_id" class="form-label">RSBSA ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="rsbsa_id" name="rsbsa_id" required>
                            </div>
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="contact_number" name="contact_number" required>
                            </div>
                            <div class="col-md-6">
                                <label for="age" class="form-label">Age <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="age" name="age" required min="18">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="civil_status" class="form-label">Civil Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="civil_status" name="civil_status" required>
                                    <option value="">Select Civil Status</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Widowed">Widowed</option>
                                    <option value="Divorced">Divorced</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="land_location" class="form-label">Land Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="land_location" name="land_location" required>
                            </div>
                            <div class="col-md-6">
                                <label for="land_size" class="form-label">Land Size (e.g., "1.5 hectares") <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="land_size" name="land_size" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="crop" class="form-label">Main Crop <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="crop" name="crop" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="register_farmer_submit_page" class="btn btn-theme">
                                <i class="fas fa-save me-1"></i> Complete Farmer Registration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>