<?php
session_start();
// Ensure connection.php exists and returns a valid $conn object (mysqli connection)
include '../includes/connection.php';

// --- STABILITY CHECK 1: Ensure database connection is successful ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? "Connection object not set"));
    // Redirect or show a maintenance page, preventing the rest of the script from executing
    header("location: database_error.php"); // Assuming you have a file for connection errors
    exit();
}
// --- END STABILITY CHECK 1 ---

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
    if ($stmt_name->execute()) { // Check execute success
        $stmt_name->bind_result($db_name);
        $stmt_name->fetch();
        if ($db_name) {
            $display_name = htmlspecialchars($db_name); // Sanitize immediately
        }
    } else {
        error_log("Failed to execute statement for user name: " . $stmt_name->error);
    }
    $stmt_name->close();
} else {
    error_log("Failed to prepare statement for user name: " . $conn->error);
}

$farmers_users = [];
$message = '';
$message_type = '';

// The registration logic block remains the same, as the form submission entry is not via this page anymore.
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
        if ($stmt_check) {
            $stmt_check->bind_param("i", $register_user_id);
            if ($stmt_check->execute()) {
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
            } else {
                 error_log("Failed to execute farmer check statement: " . $stmt_check->error);
                 $message = "Database error during farmer check.";
                 $message_type = 'danger';
            }
            $stmt_check->close();
        } else {
             error_log("Failed to prepare farmer check statement: " . $conn->error);
             $message = "Database error: Could not prepare farmer check statement.";
             $message_type = 'danger';
        }
    } else {
        $message = "Invalid User ID provided for registration.";
        $message_type = 'danger';
    }
    // Redirect is done after the deletion logic to handle both in one request cycle
}

// --- PHP DELETION LOGIC (Retained) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_farmer_submit'])) {
    $farmer_id_to_delete = $_POST['delete_farmer_id'] ?? null;

    if ($farmer_id_to_delete && is_numeric($farmer_id_to_delete)) {
        // Fetch the user_name for the success/error message before deletion
        $stmt_fetch_name = $conn->prepare("SELECT u.name FROM farmers f JOIN users u ON f.user_id = u.user_id WHERE f.farmer_id = ?");
        $user_name = 'a farmer';
        if ($stmt_fetch_name) {
            $stmt_fetch_name->bind_param("i", $farmer_id_to_delete);
            if ($stmt_fetch_name->execute()) {
                $stmt_fetch_name->bind_result($fetched_name);
                if ($stmt_fetch_name->fetch()) {
                    $user_name = htmlspecialchars($fetched_name);
                }
            } else {
                 error_log("Failed to execute name fetch for deletion: " . $stmt_fetch_name->error);
            }
            $stmt_fetch_name->close();
        } else {
            error_log("Failed to prepare name fetch for deletion: " . $conn->error);
        }


        // Delete the farmer's details (not the user account)
        $stmt_delete = $conn->prepare("DELETE FROM farmers WHERE farmer_id = ?");

        if ($stmt_delete) {
            $stmt_delete->bind_param("i", $farmer_id_to_delete);

            if ($stmt_delete->execute()) {
                $message = "Farmer details for {$user_name} (Farmer ID: {$farmer_id_to_delete}) deleted successfully! The user account remains.";
                $message_type = 'success';
            } else {
                $message = "Error deleting farmer details: " . $stmt_delete->error;
                $message_type = 'danger';
                error_log("Error deleting farmer details: " . $stmt_delete->error);
            }
            $stmt_delete->close();
        } else {
            $message = "Database error: Could not prepare farmer deletion statement.";
            $message_type = 'danger';
            error_log("Failed to prepare farmer deletion statement: " . $conn->error);
        }
    } else {
        $message = "Invalid Farmer ID provided for deletion.";
        $message_type = 'danger';
    }
    // Redirect to prevent form resubmission for delete and register actions
    header("Location: admin-view_farmers.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit();
}
// --- END PHP DELETION LOGIC ---


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
    if ($stmt_farmers->execute()) {
        $result = $stmt_farmers->get_result();
        while ($row = $result->fetch_assoc()) {
            $farmers_users[] = $row;
        }
    } else {
        $message = "Error executing fetch farmers statement: " . $stmt_farmers->error;
        $message_type = 'danger';
        error_log("Error executing fetch farmers statement: " . $stmt_farmers->error);
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
        
        /* Style for the new delete modal close button */
        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        
        /* NEW style for the clickable table row */
        .clickable-row {
            cursor: pointer;
        }
        .clickable-row:hover {
            background-color: #e2e6ea; /* Lighter hover color for clickability hint */
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
                                        <!-- Check if farmer is registered to make the row clickable for view -->
                                        <tr 
                                            class="<?php echo $farmer_user['is_registered_farmer'] ? 'clickable-row' : ''; ?>"
                                            <?php if ($farmer_user['is_registered_farmer']): ?>
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewFarmerDetailsModal"
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
                                            <?php endif; ?>
                                        >
                                            <td><?php echo $counter++; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($farmer_user['name']); ?>
                                            </td>
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
                                                    <!-- Link for registration -->
                                                    <a href="admin-register_farmer.php?user_id=<?php echo $farmer_user['user_id']; ?>" class="btn btn-sm btn-success">
                                                        <i class="fas fa-plus me-1"></i> Register Farmer
                                                    </a>
                                                <?php else: ?>
                                                    <!-- Edit and Delete Buttons for Registered Farmers -->
                                                    <div class="d-flex flex-wrap gap-1" role="group" aria-label="Farmer Actions">
                                                        <!-- EDIT BUTTON -->
                                                        <a href="admin-edit_farmer.php?farmer_id=<?php echo $farmer_user['farmer_id']; ?>" class="btn btn-sm btn-warning" onclick="event.stopPropagation();">
                                                            <i class="fas fa-edit me-1"></i> Edit
                                                        </a>
                                                        <!-- DELETE BUTTON (Triggers Modal) -->
                                                        <button type="button" class="btn btn-sm btn-danger delete-farmer-btn"
                                                            data-bs-toggle="modal" data-bs-target="#deleteFarmerModal"
                                                            data-farmer-id="<?php echo $farmer_user['farmer_id']; ?>"
                                                            data-user-name="<?php echo htmlspecialchars($farmer_user['name']); ?>"
                                                            onclick="event.stopPropagation();">
                                                            <i class="fas fa-trash-alt me-1"></i> Delete
                                                        </button>
                                                    </div>
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

    <!-- View Farmer Details Modal (Unchanged) -->
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
                <!-- MODIFIED MODAL FOOTER: Only Close button remains -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
                <!-- END MODIFIED MODAL FOOTER -->
            </div>
        </div>
    </div>

    <!-- Delete Farmer Confirmation Modal (Retained) -->
    <div class="modal fade" id="deleteFarmerModal" tabindex="-1" aria-labelledby="deleteFarmerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form action="admin-view_farmers.php" method="POST">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteFarmerModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the farmer details for <strong><span id="deleteModalUserName"></span></strong>?</p>
                        <p class="text-danger small">This action is irreversible and will remove all associated farmer data, but the user account will remain.</p>
                        <input type="hidden" name="delete_farmer_id" id="deleteModalFarmerId">
                        <input type="hidden" name="delete_farmer_submit" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // --- VIEW FARMER DETAILS MODAL LOGIC (Modified to accept event from TR) ---
            var viewFarmerDetailsModal = document.getElementById('viewFarmerDetailsModal');
            viewFarmerDetailsModal.addEventListener('show.bs.modal', function(event) {
                // Determine the trigger element. It can be the TR or the element that triggered the modal.
                var button = event.relatedTarget; 
                if (!button.classList.contains('clickable-row')) {
                    // This handles cases where the modal is manually triggered or a child button was clicked (which we've prevented via event.stopPropagation())
                    // If the modal is directly triggered by an element other than the TR, use that element.
                }

                // We get the data attributes directly from the row (which is the button/element that triggered the modal for clickable rows)
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
                var landSizeRaw = 'N/A'; 
                var landHectaresValue = 'N/A'; 

                try {
                    if (landDetailsJson) {
                        var landDetails = JSON.parse(landDetailsJson);
                        landLocation = landDetails.location || 'N/A';
                        landSizeRaw = landDetails.size || 'N/A';

                        if (landSizeRaw !== 'N/A') {
                            // Try to extract the numeric part
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
                // Construct full name
                viewFarmerDetailsModal.querySelector('#viewFullName').textContent = `${firstName} ${middleName ? middleName + ' ' : ''}${lastName}`;
                viewFarmerDetailsModal.querySelector('#viewAddress').textContent = address;
                viewFarmerDetailsModal.querySelector('#viewContactNumber').textContent = contactNumber;
                viewFarmerDetailsModal.querySelector('#viewAge').textContent = age;
                viewFarmerDetailsModal.querySelector('#viewGender').textContent = gender;
                viewFarmerDetailsModal.querySelector('#viewCivilStatus').textContent = civilStatus;
                viewFarmerDetailsModal.querySelector('#viewCrop').textContent = crop;
                viewFarmerDetailsModal.querySelector('#viewLandLocation').textContent = landLocation;
                // Display size
                viewFarmerDetailsModal.querySelector('#viewLandSize').textContent = (landHectaresValue !== 'N/A' ? landHectaresValue : 'N/A') + (landHectaresValue !== 'N/A' ? ' hectares' : '');
            });


            // --- DELETE FARMER MODAL JAVASCRIPT ---
            var deleteFarmerModal = document.getElementById('deleteFarmerModal');
            deleteFarmerModal.addEventListener('show.bs.modal', function(event) {
                // Button that triggered the modal
                var button = event.relatedTarget;
                // Extract info from data-bs-* attributes
                var farmerId = button.getAttribute('data-farmer-id');
                var userName = button.getAttribute('data-user-name');

                // Update the modal's content.
                var modalUserNameSpan = deleteFarmerModal.querySelector('#deleteModalUserName');
                var modalFarmerIdInput = deleteFarmerModal.querySelector('#deleteModalFarmerId');

                modalUserNameSpan.textContent = userName;
                modalFarmerIdInput.value = farmerId;
            });
            // --- END DELETE FARMER MODAL JAVASCRIPT ---
        });
    </script>
</body>

</html>
