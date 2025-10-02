<?php
session_start();
header('Content-Type: application/json');

// Check if the user is logged in and authorized (optional but recommended)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Database connection details (ensure these match your main file)
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "cap101";

// Create database connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// Get the POST data
$input = json_decode(file_get_contents('php://input'), true);

$application_id = $input['application_id'] ?? null;
$status = $input['status'] ?? null;
$qr_code_data = $input['qr_code_data'] ?? null; // This will be null for rejected/pending

if (!$application_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing application ID or status.']);
    $conn->close();
    exit();
}

// Prepare the update statement
$sql = "UPDATE assistance_applications SET status = ?, qr_code_data = ? WHERE application_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    $conn->close();
    exit();
}

// Bind parameters and execute
// 's' for status (string), 's' for qr_code_data (string or null), 'i' for application_id (integer)
$stmt->bind_param("ssi", $status, $qr_code_data, $application_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>