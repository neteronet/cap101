<?php
session_start();

include '../includes/connection.php';

// Check if user is logged in and is MAO
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id']) || $_SESSION['user_type'] !== 'mao') {
    header("location: municipal-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$claimer_id = $user_id; // The MAO marking the claim

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farmer_id_input = trim($_POST['farmer_id']);
    $approved_date = trim($_POST['approved_date']);

    // Parse Farmer ID: FRM-XXXXXXXXX to user_id
    if (preg_match('/^FRM-(\d{9})$/', $farmer_id_input, $matches)) {
        $user_id_parsed = (int)$matches[1];

        // Validate approved_date format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $approved_date)) {
            // Find the application
            $stmt = $conn->prepare("
                SELECT application_id, user_id, status
                FROM assistance_applications
                WHERE user_id = ? AND DATE(approval_date) = ? AND status = 'Approved'
                ORDER BY approval_date DESC
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param("is", $user_id_parsed, $approved_date);
                $stmt->execute();
                $stmt->bind_result($application_id, $app_user_id, $status);
                if ($stmt->fetch()) {
                    // Insert into subsidy_claims
                    $insert_stmt = $conn->prepare("
                        INSERT INTO subsidy_claims (application_id, user_id, claimer_id, notes)
                        VALUES (?, ?, ?, ?)
                    ");
                    $notes = 'Manually marked as claimed';
                    if ($insert_stmt) {
                        $insert_stmt->bind_param("iiis", $application_id, $app_user_id, $claimer_id, $notes);
                        if ($insert_stmt->execute()) {
                            $message = '<div class="alert alert-success">Claim successfully marked for Farmer ID ' . htmlspecialchars($farmer_id_input) . ' on ' . htmlspecialchars($approved_date) . '.</div>';
                        } else {
                            $message = '<div class="alert alert-danger">Error inserting claim: ' . $conn->error . '</div>';
                        }
                        $insert_stmt->close();
                    } else {
                        $message = '<div class="alert alert-danger">Error preparing insert statement.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-warning">No approved application found for Farmer ID ' . htmlspecialchars($farmer_id_input) . ' on ' . htmlspecialchars($approved_date) . '.</div>';
                }
                $stmt->close();
            } else {
                $message = '<div class="alert alert-danger">Error preparing select statement.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Invalid approved date format. Use YYYY-MM-DD.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Invalid Farmer ID format. Use FRM-XXXXXXXXX.</div>';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Claim Marking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Manual Claim Marking</h1>
        <p>Use this form to manually mark a subsidy as claimed based on Farmer ID and Approved Date.</p>
        <?php echo $message; ?>
        <form method="post">
            <div class="mb-3">
                <label for="farmer_id" class="form-label">Farmer ID (e.g., FRM-000000002)</label>
                <input type="text" class="form-control" id="farmer_id" name="farmer_id" required>
            </div>
            <div class="mb-3">
                <label for="approved_date" class="form-label">Approved Date (YYYY-MM-DD)</label>
                <input type="date" class="form-control" id="approved_date" name="approved_date" required>
            </div>
            <button type="submit" class="btn btn-primary">Mark as Claimed</button>
        </form>
        <a href="municipal-dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
    </div>
</body>
</html>
