<?php
session_start();
header('Content-Type: application/json'); // Set header for JSON response

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Ensure the user is logged in and is a municipal officer (optional, but good practice)
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not authenticated.';
    echo json_encode($response);
    exit();
}

// Database connection details
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "cap101";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    $response['message'] = 'Database connection error.';
    echo json_encode($response);
    exit();
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Invalid JSON input.';
    echo json_encode($response);
    $conn->close();
    exit();
}

$application_id = $data['application_id'] ?? null;
$new_status = $data['status'] ?? null;
$qr_code_data = $data['qr_code_data'] ?? null; // Will be null for reject/pending

if (!$application_id || !$new_status) {
    $response['message'] = 'Missing application ID or status.';
    echo json_encode($response);
    $conn->close();
    exit();
}

$municipal_officer_id = $_SESSION['user_id']; // The ID of the logged-in municipal officer
$current_datetime = date('Y-m-d H:i:s'); // Current timestamp

// Prepare SQL update statement
if ($new_status == 'Approved') {
    $stmt = $conn->prepare("UPDATE assistance_applications SET status = ?, approved_by_id = ?, approval_date = ?, qr_code_data = ? WHERE application_id = ?");
    $stmt->bind_param("sisss", $new_status, $municipal_officer_id, $current_datetime, $qr_code_data, $application_id);
} else { // 'Rejected' or 'Pending'
    $stmt = $conn->prepare("UPDATE assistance_applications SET status = ?, approved_by_id = NULL, approval_date = NULL, qr_code_data = NULL WHERE application_id = ?");
    $stmt->bind_param("si", $new_status, $application_id);
}

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Application status updated successfully.';
} else {
    error_log("Error updating application status: " . $stmt->error);
    $response['message'] = 'Failed to update application status: ' . $stmt->error;
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>