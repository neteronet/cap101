<?php
// api/get_subsidy_details.php
session_start();
header('Content-Type: application/json');
include '../includes/connection.php'; // Adjust path as necessary

$response = ['success' => false, 'message' => 'Invalid request.', 'details' => null];

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

    if (empty($application_id) || empty($user_id)) {
        $response['message'] = 'Missing application or user ID.';
        echo json_encode($response);
        exit();
    }

    // 1. Fetch Subsidy Details
    $stmt_subsidy = $conn->prepare("
        SELECT 
            aa.assistance_type, 
            aa.status, 
            aa.claimed,
            u.name
        FROM assistance_applications aa
        JOIN users u ON aa.user_id = u.user_id
        WHERE aa.application_id = ? AND aa.user_id = ?
    ");

    if ($stmt_subsidy) {
        $stmt_subsidy->bind_param("ii", $application_id, $user_id);
        $stmt_subsidy->execute();
        $result = $stmt_subsidy->get_result();
        $details = $result->fetch_assoc();
        $stmt_subsidy->close();

        if ($details) {
            $response['success'] = true;
            $response['message'] = 'Details fetched successfully.';
            $response['details'] = [
                'farmer_id' => $user_id,
                'application_id' => $application_id,
                'farmer_name' => htmlspecialchars($details['name']),
                'subsidy_type' => htmlspecialchars($details['assistance_type']),
                'current_status' => htmlspecialchars($details['status']),
                'claim_count' => (int)$details['claimed']
            ];
        } else {
            $response['message'] = 'No matching approved subsidy found.';
        }
    } else {
        $response['message'] = 'Database error: ' . $conn->error;
    }
}

$conn->close();
echo json_encode($response);
?>