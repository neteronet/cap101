<?php
// api/update_subsidy_claim.php
session_start();
header('Content-Type: application/json');
include '../includes/connection.php'; // Adjust path as necessary

$response = ['success' => false, 'message' => 'Invalid request.'];

// Security check: Only municipal users can access this API
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit();
}

// Additional check for user_type
$stmt_user = $conn->prepare("SELECT user_type FROM users WHERE user_id = ?");
if ($stmt_user) {
    $stmt_user->bind_param("i", $_SESSION['user_id']);
    $stmt_user->execute();
    $stmt_user->bind_result($user_type);
    $stmt_user->fetch();
    $stmt_user->close();

    if ($user_type !== 'mao') {
        $response['message'] = 'Unauthorized access.';
        echo json_encode($response);
        exit();
    }
} else {
    $response['message'] = 'Database error.';
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

    // Update the application status to 'Claimed' if not already
    $new_status = 'Claimed';

    $update_stmt = $conn->prepare("
        UPDATE assistance_applications
        SET
            status = ?
        WHERE
            application_id = ?
            AND user_id = ?
            AND (status = 'Approved' OR status = 'Claimed')
    ");

    if ($update_stmt) {
        $update_stmt->bind_param("sii", $new_status, $application_id, $user_id);

        if ($update_stmt->execute()) {
            if ($update_stmt->affected_rows > 0) {
                // Now insert a new claim record into subsidy_claims
                $insert_claim_stmt = $conn->prepare("
                    INSERT INTO subsidy_claims (application_id, user_id, claimer_id, notes)
                    VALUES (?, ?, ?, ?)
                ");
                $notes = 'Claimed via QR scan'; // Optional notes
                if ($insert_claim_stmt) {
                    $insert_claim_stmt->bind_param("iiis", $application_id, $user_id, $claimer_id, $notes);
                    if ($insert_claim_stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Subsidy successfully marked as claimed!';
                    } else {
                        $response['message'] = 'Claim logged, but database error: ' . $insert_claim_stmt->error;
                    }
                    $insert_claim_stmt->close();
                } else {
                    $response['message'] = 'Database error: Could not prepare claim insert statement.';
                }
            } else {
                // This means the application was not approved or IDs didn't match
                $response['message'] = 'Claim failed. The subsidy is not eligible for claiming.';
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