<?php
header('Content-Type: application/json');
include 'connection.php'; // Include your database connection

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $status = $_POST['status'] ?? null;
    $qr_code_data = $_POST['qr_code_data'] ?? null;

    if ($id && $status) {
        $stmt = null;
        if ($status === 'Approved') {
            // For 'Approved' status, update date_approved and qr_code_data
            $stmt = $conn->prepare("UPDATE subsidies SET status = ?, qr_code_data = ?, date_approved = NOW() WHERE id = ?");
            $stmt->bind_param("ssi", $status, $qr_code_data, $id);
        } elseif ($status === 'Pending') {
            // For 'Pending' status (when sending back for review), clear qr_code_data and date_approved
            $stmt = $conn->prepare("UPDATE subsidies SET status = ?, qr_code_data = NULL, date_approved = NULL, date_claimed = NULL WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
        } else {
            // For 'Rejected' or other statuses, just update the status
            $stmt = $conn->prepare("UPDATE subsidies SET status = ?, qr_code_data = NULL, date_approved = NULL, date_claimed = NULL WHERE id = ?"); // Clear QR and dates if rejected
            $stmt->bind_param("si", $status, $id);
        }

        if ($stmt && $stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Subsidy status updated successfully.';
            } else {
                $response['message'] = 'No rows updated. Check if ID exists or status is already the same.';
            }
        } else {
            $response['message'] = 'Database update failed: ' . ($stmt ? $stmt->error : $conn->error);
        }
        if ($stmt) {
            $stmt->close();
        }
    } else {
        $response['message'] = 'Missing ID or status in request.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>