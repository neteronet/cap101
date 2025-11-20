<?php
// api/get_subsidy_details.php
// Suppress any output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, we'll handle them
ob_start(); // Start output buffering to catch any unexpected output

// Helper function to output clean JSON
function outputJson($data) {
    ob_end_clean(); // Clear any output
    echo json_encode($data);
    exit();
}

session_start();
header('Content-Type: application/json');

// Include connection file
$conn = null;
try {
    // Include connection file - any output (like die() messages) will be caught by ob_start() at top
    include '../../includes/connection.php';
    
    // Check if connection was established
    if (!isset($conn)) {
        outputJson(['success' => false, 'message' => 'Database connection failed. Connection object not created.', 'details' => null]);
    }
    
    // Check if connection has errors
    if ($conn->connect_error) {
        outputJson(['success' => false, 'message' => 'Database connection error: ' . $conn->connect_error, 'details' => null]);
    }
} catch (Exception $e) {
    outputJson(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage(), 'details' => null]);
} catch (Error $e) {
    outputJson(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage(), 'details' => null]);
}

// Check if any output was generated (like from die() in connection.php)
$buffer_content = ob_get_contents();
if (!empty($buffer_content)) {
    // If there's any output, it's likely from die() in connection.php
    ob_clean(); // Clear the die() output
    // Log the actual error for debugging (but don't expose it to user for security)
    error_log("Connection.php output: " . $buffer_content);
    outputJson(['success' => false, 'message' => 'Database connection failed. Please ensure MySQL is running and database "cap101" exists.', 'details' => null]);
}

$response = ['success' => false, 'message' => 'Invalid request.', 'details' => null];

// Security check: Only municipal users can access this API
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    $response['message'] = 'Unauthorized access.';
    outputJson($response);
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
        outputJson($response);
    }
} else {
    $response['message'] = 'Database error.';
    outputJson($response);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $application_id = isset($_POST['application_id']) ? filter_input(INPUT_POST, 'application_id', FILTER_SANITIZE_NUMBER_INT) : null;

    if (empty($user_id)) {
        $response['message'] = 'Missing user ID.';
        outputJson($response);
    }

    // If application_id is provided, fetch specific application
    // Otherwise, fetch the latest approved/claimed application for the user
    if ($application_id) {
        // Fetch specific application
        $stmt_subsidy = $conn->prepare("
            SELECT
                aa.application_id,
                aa.assistance_type,
                aa.status,
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
        } else {
            $response['message'] = 'Database error: ' . $conn->error;
            outputJson($response);
        }
    } else {
        // Fetch latest approved/claimed application for the user
        $stmt_subsidy = $conn->prepare("
            SELECT
                aa.application_id,
                aa.assistance_type,
                aa.status,
                u.name
            FROM assistance_applications aa
            JOIN users u ON aa.user_id = u.user_id
            WHERE aa.user_id = ? AND (aa.status = 'Approved' OR aa.status = 'Claimed')
            ORDER BY aa.application_id DESC
            LIMIT 1
        ");

        if ($stmt_subsidy) {
            $stmt_subsidy->bind_param("i", $user_id);
            $stmt_subsidy->execute();
            $result = $stmt_subsidy->get_result();
            $details = $result->fetch_assoc();
            $stmt_subsidy->close();
        } else {
            $response['message'] = 'Database error: ' . $conn->error;
            outputJson($response);
        }
    }

    if ($details) {
        $found_application_id = $details['application_id'];
        
        // 2. Fetch Claim Count from subsidy_claims
        $stmt_claims = $conn->prepare("
            SELECT COUNT(*) as claim_count
            FROM subsidy_claims
            WHERE application_id = ?
        ");
        $claim_count = 0;
        if ($stmt_claims) {
            $stmt_claims->bind_param("i", $found_application_id);
            $stmt_claims->execute();
            $stmt_claims->bind_result($claim_count);
            $stmt_claims->fetch();
            $stmt_claims->close();
        }

        $response['success'] = true;
        $response['message'] = 'Details fetched successfully.';
        $response['details'] = [
            'farmer_id' => $user_id,
            'application_id' => $found_application_id,
            'farmer_name' => htmlspecialchars($details['name']),
            'subsidy_type' => htmlspecialchars($details['assistance_type']),
            'current_status' => htmlspecialchars($details['status']),
            'claim_count' => (int)$claim_count
        ];
    } else {
        $response['message'] = 'No matching approved or claimed subsidy found for this Farmer ID.';
    }
}

$conn->close();
outputJson($response);
?>
