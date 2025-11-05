<?php
session_start();
include '../includes/connection.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: admin-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = 'Admin'; // Default fallback

// Fetch admin's name
$stmt_name = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
if ($stmt_name) {
    $stmt_name->bind_param("i", $user_id);
    $stmt_name->execute();
    $stmt_name->bind_result($db_name);
    $stmt_name->fetch();
    if ($db_name) {
        $display_name = htmlspecialchars($db_name); // Sanitize immediately
    }
    $stmt_name->close();
} else {
    error_log("Failed to prepare statement for user name: " . $conn->error);
}

$farmers_users = [];
$message = '';
$message_type = '';

// Handle farmer registration submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_farmer_submit'])) {
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

    if ($register_user_id && is_numeric($register_user_id)) {
        // Prepare land_details as JSON
        $land_details_array = [
            'location' => $land_location,
            'size' => $land_size
        ];
        $land_details_json = json_encode($land_details_array);

        // Check if farmer is already registered
        $stmt_check = $conn->prepare("SELECT farmer_id FROM farmers WHERE user_id = ?");
        $stmt_check->bind_param("i", $register_user_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $message = "Farmer for this user ID is already registered.";
            $message_type = 'warning';
        } else {
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
                    $message = "Farmer details registered successfully!";
                    $message_type = 'success';
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
    // Redirect to prevent form resubmission
    header("Location: admin-view_farmers.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit();
}


// Fetch all users with user_type = 'farmer' and their registration status in the 'farmers' table,
// including all farmer details for the view modal
$stmt_farmers = $conn->prepare("
    SELECT
        u.user_id,
        u.name,
        u.username,
        u.created_at,
        f.farmer_id,
        f.rsbsa_id,
        f.first_name,
        f.middle_name,
        f.last_name,
        f.address,
        f.contact_number,
        f.land_details,
        f.age,
        f.gender,
        f.civil_status,
        f.crop,
        f.farmer_id IS NOT NULL AS is_registered_farmer
    FROM
        users u
    LEFT JOIN
        farmers f ON u.user_id = f.user_id
    WHERE
        u.user_type = 'farmer'
    ORDER BY
        u.created_at DESC
");

if ($stmt_farmers) {
    $stmt_farmers->execute();
    $result = $stmt_farmers->get_result();
    while ($row = $result->fetch_assoc()) {
        $farmers_users[] = $row;
    }
    $stmt_farmers->close();
} else {
    $message = "Error fetching farmers: " . $conn->error;
    $message_type = 'danger';
    error_log("Failed to prepare statement for fetching farmers: " . $conn->error);
}

// Check for messages from redirects
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
    <title>Admin - View Farmers</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Custom Styles (re-use from add-user.php or link a common CSS file) -->
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

        .table-responsive {
            margin-top: 1rem;
        }

        .status-badge {
            padding: 0.35em 0.65em;
            border-radius: 0.375rem;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            display: inline-block;
        }

        .status-registered {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-not-registered {
            background-color: #f8d7da;
            color: #842029;
        }

        .modal-dialog {
            margin-top: 70px;
        }

        #registerFarmerModal .modal-dialog {
            margin-top: 70px;
            max-width: 900px;
        }

        #registerFarmerModal .modal-body {
            padding-left: 2rem;
            padding-right: 2rem;
        }

        #registerFarmerModal .modal-dialog {
            margin-top: 70px;
            max-width: 900px;
        }

        #registerFarmerModal .modal-body {
            padding-left: 2rem;
            padding-right: 2rem;
        }

        #registerFarmerModal .modal-header {
            padding-left: 2rem;
            padding-right: 2rem;
        }

        #registerFarmerModal .modal-footer {
            padding-left: 2rem;
            padding-right: 2rem;
        }

        #viewFarmerDetailsModal .modal-dialog {
            margin-top: 70px;
            max-width: 800px; /* Adjust as needed */
        }
        #viewFarmerDetailsModal .modal-body {
            padding: 2rem;
        }
        #viewFarmerDetailsModal .modal-header,
        #viewFarmerDetailsModal .modal-footer {
            padding: 1rem 2rem;
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
            <h1 class="page-title">Recent Farmers</h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">List of Registered Farmers</h5>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Full Name</th>
                                    <th>Username (Email)</th>
                                    <th>Date Registered (User)</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($farmers_users)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No farmers registered yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($farmers_users as $farmer_user): ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($farmer_user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($farmer_user['username']); ?></td>
                                            <td><?php echo htmlspecialchars(date('M d, Y H:i A', strtotime($farmer_user['created_at']))); ?></td>
                                            <td>
                                                <?php if ($farmer_user['is_registered_farmer']): ?>
                                                    <span class="status-badge status-registered">Registered</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-not-registered">Not Registered</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$farmer_user['is_registered_farmer']): ?>
                                                    <!-- Changed to button to trigger modal -->
                                                    <button type="button" class="btn btn-sm btn-success register-farmer-btn"
                                                        data-bs-toggle="modal" data-bs-target="#registerFarmerModal"
                                                        data-user-id="<?php echo $farmer_user['user_id']; ?>"
                                                        data-user-name="<?php echo htmlspecialchars($farmer_user['name']); ?>">
                                                        <i class="fas fa-plus me-1"></i> Register Farmer
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-info view-farmer-details-btn"
                                                        data-bs-toggle="modal" data-bs-target="#viewFarmerDetailsModal"
                                                        data-farmer-id="<?php echo $farmer_user['farmer_id']; ?>"
                                                        data-user-name="<?php echo htmlspecialchars($farmer_user['name']); ?>"
                                                        data-rsbsa-id="<?php echo htmlspecialchars($farmer_user['rsbsa_id']); ?>"
                                                        data-first-name="<?php echo htmlspecialchars($farmer_user['first_name']); ?>"
                                                        data-middle-name="<?php echo htmlspecialchars($farmer_user['middle_name']); ?>"
                                                        data-last-name="<?php echo htmlspecialchars($farmer_user['last_name']); ?>"
                                                        data-address="<?php echo htmlspecialchars($farmer_user['address']); ?>"
                                                        data-contact-number="<?php echo htmlspecialchars($farmer_user['contact_number']); ?>"
                                                        data-land-details='<?php echo htmlspecialchars($farmer_user['land_details']); ?>'
                                                        data-age="<?php echo htmlspecialchars($farmer_user['age']); ?>"
                                                        data-gender="<?php echo htmlspecialchars($farmer_user['gender']); ?>"
                                                        data-civil-status="<?php echo htmlspecialchars($farmer_user['civil_status']); ?>"
                                                        data-crop="<?php echo htmlspecialchars($farmer_user['crop']); ?>"
                                                    >
                                                        <i class="fas fa-eye me-1"></i> View Details
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Register Farmer Modal -->
    <div class="modal fade" id="registerFarmerModal" tabindex="-1" aria-labelledby="registerFarmerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registerFarmerModalLabel">Register Farmer Details for <span id="modalUserName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="admin-view_farmers.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="register_user_id" id="modalUserId">

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
                        <div class="mb-3">
                            <label for="crop" class="form-label">Main Crop <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="crop" name="crop" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="register_farmer_submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Register Farmer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Farmer Details Modal -->
    <div class="modal fade" id="viewFarmerDetailsModal" tabindex="-1" aria-labelledby="viewFarmerDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewFarmerDetailsModalLabel">Farmer Details for <span id="viewModalUserName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <strong>RSBSA ID:</strong> <span id="viewRsbsaId"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Full Name:</strong> <span id="viewFullName"></span>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <strong>Address:</strong> <span id="viewAddress"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Contact Number:</strong> <span id="viewContactNumber"></span>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <strong>Age:</strong> <span id="viewAge"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Gender:</strong> <span id="viewGender"></span>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <strong>Civil Status:</strong> <span id="viewCivilStatus"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Main Crop:</strong> <span id="viewCrop"></span>
                        </div>
                    </div>
                    <h6 class="mt-3">Land Details:</h6>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <strong>Location:</strong> <span id="viewLandLocation"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Size:</strong> <span id="viewLandSize"></span>
                        </div>
                    </div>
                </div>
                <!-- MODIFIED MODAL FOOTER: Added Edit Details button -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="editFarmerBtn">
                        <i class="fas fa-edit me-1"></i> Edit Details
                    </button>
                </div>
                <!-- END MODIFIED MODAL FOOTER -->
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var registerFarmerModal = document.getElementById('registerFarmerModal');
            registerFarmerModal.addEventListener('show.bs.modal', function(event) {
                // Button that triggered the modal
                var button = event.relatedTarget;
                // Extract info from data-bs-* attributes
                var userId = button.getAttribute('data-user-id');
                var userName = button.getAttribute('data-user-name');

                // Update the modal's content.
                var modalTitle = registerFarmerModal.querySelector('#modalUserName');
                var modalUserIdInput = registerFarmerModal.querySelector('#modalUserId');

                modalTitle.textContent = userName;
                modalUserIdInput.value = userId;

                // Clear previous form data when modal opens
                var form = registerFarmerModal.querySelector('form');
                form.reset();
                // You might want to pre-fill first_name, last_name, etc., if you have that data for the user in the 'users' table.
                // For now, we'll just clear it for a fresh entry.
            });

            var viewFarmerDetailsModal = document.getElementById('viewFarmerDetailsModal');
            viewFarmerDetailsModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget; // Button that triggered the modal

                // Get the farmer_id for the edit button
                var farmerId = button.getAttribute('data-farmer-id'); 

                // Extract info from data-bs-* attributes
                var userName = button.getAttribute('data-user-name');
                var rsbsaId = button.getAttribute('data-rsbsa-id');
                var firstName = button.getAttribute('data-first-name');
                var middleName = button.getAttribute('data-middle-name');
                var lastName = button.getAttribute('data-last-name');
                var address = button.getAttribute('data-address');
                var contactNumber = button.getAttribute('data-contact-number');
                var landDetailsJson = button.getAttribute('data-land-details');
                var age = button.getAttribute('data-age');
                var gender = button.getAttribute('data-gender');
                var civilStatus = button.getAttribute('data-civil-status');
                var crop = button.getAttribute('data-crop');

                // Parse land details JSON
                var landLocation = 'N/A';
                var landSizeRaw = 'N/A'; // Store the raw size string
                var landHectaresValue = 'N/A'; // Store just the numeric part for display

                try {
                    if (landDetailsJson) {
                        var landDetails = JSON.parse(landDetailsJson);
                        landLocation = landDetails.location || 'N/A';
                        landSizeRaw = landDetails.size || 'N/A';

                        if (landSizeRaw !== 'N/A') {
                            // Try to extract the numeric part, whether 'hectares' is present or not
                            const match = landSizeRaw.match(/(\d+(\.\d+)?)/);
                            if (match && match[1]) {
                                landHectaresValue = match[1];
                            }
                        }
                    }
                } catch (e) {
                    console.error("Error parsing land_details JSON:", e);
                }

                // Update the modal's content
                viewFarmerDetailsModal.querySelector('#viewModalUserName').textContent = userName;
                viewFarmerDetailsModal.querySelector('#viewRsbsaId').textContent = rsbsaId;
                // Construct full name, handling cases where middle name might be empty
                viewFarmerDetailsModal.querySelector('#viewFullName').textContent = `${firstName} ${middleName ? middleName + ' ' : ''}${lastName}`;
                viewFarmerDetailsModal.querySelector('#viewAddress').textContent = address;
                viewFarmerDetailsModal.querySelector('#viewContactNumber').textContent = contactNumber;
                viewFarmerDetailsModal.querySelector('#viewAge').textContent = age;
                viewFarmerDetailsModal.querySelector('#viewGender').textContent = gender;
                viewFarmerDetailsModal.querySelector('#viewCivilStatus').textContent = civilStatus;
                viewFarmerDetailsModal.querySelector('#viewCrop').textContent = crop;
                viewFarmerDetailsModal.querySelector('#viewLandLocation').textContent = landLocation;
                // Always append " hectares" for consistent display
                viewFarmerDetailsModal.querySelector('#viewLandSize').textContent = (landHectaresValue !== 'N/A' ? landHectaresValue : 'N/A') + ' hectares';
                
                // --- NEW EDIT BUTTON LOGIC ---
                var editButton = viewFarmerDetailsModal.querySelector('#editFarmerBtn');
                if (editButton) {
                    // Set the redirect URL for the Edit button using the retrieved farmerId
                    // NOTE: You must create 'admin-edit_farmer.php' to handle the editing.
                    editButton.onclick = function() {
                        window.location.href = 'admin-edit_farmer.php?farmer_id=' + farmerId;
                    };
                }
                // --- END NEW EDIT BUTTON LOGIC ---
            });

            // Clear the onclick action when the modal hides to prevent stale data
            viewFarmerDetailsModal.addEventListener('hidden.bs.modal', function() {
                var editButton = viewFarmerDetailsModal.querySelector('#editFarmerBtn');
                if (editButton) {
                    editButton.onclick = null;
                }
            });
        });
    </script>
</body>

</html>