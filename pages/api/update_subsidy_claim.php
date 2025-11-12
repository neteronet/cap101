<?php
// api/update_subsidy_claim.php
session_start();
header('Content-Type: application/json');
include '../includes/connection.php'; // Adjust path as necessary

$response = ['success' => false, 'message' => 'Invalid request.'];

// Security check: Only municipal users can access this API
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id']) /* Add check for user_type='municipal' if needed */) {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id']) && isset($_POST['user_id'])) {
    $application_id = filter_input(INPUT_POST, 'application_id', FILTER_SANITIZE_NUMBER_INT);
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $claimer_id = $_SESSION['user_id']; // The municipal user ID

    if (empty($application_id) || empty($user_id)) {
        $response['message'] = 'Missing application or user ID.';
        echo json_encode($response);
        exit();
    }

    // Update the application status
    $new_status = 'Claimed';
    $claimed_flag = 1;

    $update_stmt = $conn->prepare("
        UPDATE assistance_applications
        SET 
            status = ?, 
            claimed = ?, 
            claimed_by_user_id = ?, 
            claimed_date = NOW()
        WHERE 
            application_id = ? 
            AND user_id = ? 
            AND claimed = 0
            AND status = 'Approved'
    ");

    if ($update_stmt) {
        $update_stmt->bind_param("siiii", $new_status, $claimed_flag, $claimer_id, $application_id, $user_id);
        
        if ($update_stmt->execute()) {
            if ($update_stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Subsidy successfully marked as claimed!';
            } else {
                // This means the application was already claimed, not approved, or IDs didn't match
                $response['message'] = 'Claim failed. The subsidy might be already claimed or not yet approved.';
            }
        } else {
            $response['message'] = 'Database execution error: ' . $update_stmt->error;
        }
        $update_stmt->close();
    } else {
        $response['message'] = 'Database error: Could not prepare statement.';
    }
}

$conn->close();
echo json_encode($response);
?>