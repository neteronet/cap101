<?php
// api/update_subsidy_claim.php
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
        outputJson(['success' => false, 'message' => 'Database connection failed. Connection object not created.']);
    }
    
    // Check if connection has errors
    if ($conn->connect_error) {
        outputJson(['success' => false, 'message' => 'Database connection error: ' . $conn->connect_error]);
    }
} catch (Exception $e) {
    outputJson(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
} catch (Error $e) {
    outputJson(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
}

// Check if any output was generated (like from die() in connection.php)
$buffer_content = ob_get_contents();
if (!empty($buffer_content)) {
    // If there's any output, it's likely from die() in connection.php
    ob_clean(); // Clear the die() output
    // Log the actual error for debugging (but don't expose it to user for security)
    error_log("Connection.php output: " . $buffer_content);
    outputJson(['success' => false, 'message' => 'Database connection failed. Please ensure MySQL is running and database "cap101" exists.']);
}

$response = ['success' => false, 'message' => 'Invalid request.'];

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
 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id']) && isset($_POST['user_id'])) {
    $application_id = filter_input(INPUT_POST, 'application_id', FILTER_SANITIZE_NUMBER_INT);
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $claimer_id = $_SESSION['user_id']; // The municipal user ID

    if (empty($application_id) || empty($user_id)) {
        $response['message'] = 'Missing application or user ID.';
        outputJson($response);
    }

    // First, verify the application exists and is eligible for claiming
    $check_stmt = $conn->prepare("
        SELECT application_id, status
        FROM assistance_applications
        WHERE application_id = ?
            AND user_id = ?
            AND (status = 'Approved' OR status = 'Claimed')
    ");

    if ($check_stmt) {
        $check_stmt->bind_param("ii", $application_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $application = $check_result->fetch_assoc();
        $check_stmt->close();

        if ($application) {
            // Get the current maximum claimed value for this farmer
            $max_claimed_stmt = $conn->prepare("SELECT MAX(claimed) FROM assistance_applications WHERE user_id = ?");
            $current_claimed = 0;
            if ($max_claimed_stmt) {
                $max_claimed_stmt->bind_param("i", $user_id);
                $max_claimed_stmt->execute();
                $max_claimed_stmt->bind_result($current_claimed);
                $max_claimed_stmt->fetch();
                $max_claimed_stmt->close();
            }
            $new_claimed = ($current_claimed ? $current_claimed : 0) + 1;

            // Update the claimed count only for the specific application being claimed
            $update_claimed_stmt = $conn->prepare("UPDATE assistance_applications SET claimed = ? WHERE application_id = ? AND user_id = ?");
            if ($update_claimed_stmt) {
                $update_claimed_stmt->bind_param("iii", $new_claimed, $application_id, $user_id);
                $update_claimed_stmt->execute();
                $update_claimed_stmt->close();
            }

            // Application is eligible - always insert a new claim record
            $insert_claim_stmt = $conn->prepare("
                INSERT INTO subsidy_claims (application_id, user_id, claimer_id, notes)
                VALUES (?, ?, ?, ?)
            ");
            $notes = 'Claimed via QR scan'; // Optional notes
            if ($insert_claim_stmt) {
                $insert_claim_stmt->bind_param("iiis", $application_id, $user_id, $claimer_id, $notes);
                if ($insert_claim_stmt->execute()) {
                    // Now update the status to 'Claimed' if it's not already
                    $new_status = 'Claimed';
                    $update_stmt = $conn->prepare("
                        UPDATE assistance_applications
                        SET status = ?
                        WHERE application_id = ?
                            AND user_id = ?
                            AND status = 'Approved'
                    ");
                    if ($update_stmt) {
                        $update_stmt->bind_param("sii", $new_status, $application_id, $user_id);
                        $update_stmt->execute(); // Don't check affected_rows - status might already be 'Claimed'
                        $update_stmt->close();
                    }

                    $response['success'] = true;
                    $response['message'] = 'Subsidy claim successfully saved to database!';
                } else {
                    $response['message'] = 'Database error while saving claim: ' . $insert_claim_stmt->error;
                }
                $insert_claim_stmt->close();
            } else {
                $response['message'] = 'Database error: Could not prepare claim insert statement.';
            }
        } else {
            // Application not found or not eligible
            $response['message'] = 'Claim failed. The subsidy is not eligible for claiming or does not exist.';
        }
    } else {
        $response['message'] = 'Database error: Could not prepare verification statement.';
    }
}

$conn->close();
outputJson($response);
?>