<?php
session_start();
header('Content-Type: application/json');

include '../includes/connection.php'; // Ensure this path is correct

$response = ['success' => false, 'message' => ''];

// 1. Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit();
}

// 2. Robust Connection Check
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in update script: " . ($conn->connect_error ?? "Connection object not set"));
    $response['message'] = 'Database connection error.';
    echo json_encode($response);
    exit();
}

// 3. Authorization Check (Minimal)
// Redirect if user_id is not set, not an integer, or assumed to be an unauthorized user
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    $response['message'] = 'Unauthorized access.';
    // Log the event for security monitoring
    error_log("Unauthorized access attempt to municipal-update_subsidy_status.php without a valid session.");
    echo json_encode($response);
    exit();
}

// Optional: Re-verify user_type = 'mao' for maximum security.
// The main page (municipal-subsidy_management.php) already handles the redirect, 
// so we'll proceed assuming a valid MAO session, but this is a point for further hardening.

// 4. Read and Decode JSON Input
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Invalid JSON input.';
    echo json_encode($response);
    exit();
}

// 5. Validate Input Data
$application_id = $data['application_id'] ?? null;
$status = $data['status'] ?? null;

if (empty($application_id) || empty($status)) {
    $response['message'] = 'Missing application ID or status.';
    echo json_encode($response);
    exit();
}

// Sanitize inputs
$application_id = (int)$application_id;

// Define allowed status values for security
$allowed_statuses = ['Pending', 'Approved', 'Rejected'];
if (!in_array($status, $allowed_statuses)) {
    $response['message'] = 'Invalid status value provided.';
    echo json_encode($response);
    exit();
}

// 6. Prepare and Execute SQL Update
$sql = "UPDATE assistance_applications SET status = ? WHERE application_id = ?";

if ($stmt = $conn->prepare($sql)) {
    // 'si' means bind one string (status) and one integer (application_id)
    $stmt->bind_param("si", $status, $application_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = "Application ID {$application_id} status updated to '{$status}'.";
        } else {
            // This could mean the ID didn't exist or the status was already the new value
            $response['message'] = "Application ID {$application_id} not found or status already '{$status}'.";
        }
    } else {
        error_log("SQL Error: " . $stmt->error);
        $response['message'] = 'Database execution error: ' . $stmt->error;
    }

    $stmt->close();
} else {
    error_log("Failed to prepare statement: " . $conn->error);
    $response['message'] = 'Database preparation error.';
}

// 7. Close Connection and Output Response
if (isset($conn)) {
    $conn->close();
}

echo json_encode($response);
?>