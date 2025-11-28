<?php
/**
 * Notification Helper Functions
 * 
 * This file contains reusable functions for creating and managing notifications
 * for farmers in the system.
 */

/**
 * Ensure the notifications table exists.
 * This uses the same schema as database/notifications_table.sql but runs
 * safely with IF NOT EXISTS so it won't overwrite existing data.
 */
function ensureNotificationsTable($conn) {
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }

    $sql = "CREATE TABLE IF NOT EXISTS `notifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `farmer_id` INT(11) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `message` TEXT NOT NULL,
        `status` ENUM('unread', 'read') NOT NULL DEFAULT 'unread',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_farmer_id` (`farmer_id`),
        KEY `idx_status` (`status`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if (!$conn->query($sql)) {
        error_log("Failed to ensure notifications table exists: " . $conn->error);
        return false;
    }

    return true;
}

/**
 * Create a notification for a farmer
 * 
 * @param mysqli $conn Database connection object
 * @param int $farmer_id The user_id of the farmer
 * @param string $title Notification title
 * @param string $message Notification message
 * @return bool|int Returns notification ID on success, false on failure
 */
function createNotification($conn, $farmer_id, $title, $message) {
    // Validate inputs
    if (!isset($conn) || $conn->connect_error) {
        error_log("Database connection error in createNotification");
        return false;
    }
    
    if (empty($farmer_id) || !is_numeric($farmer_id)) {
        error_log("Invalid farmer_id in createNotification: " . $farmer_id);
        return false;
    }
    
    if (empty($title) || empty($message)) {
        error_log("Empty title or message in createNotification");
        return false;
    }
    
    // Ensure notifications table exists (in case SQL file was not run manually)
    ensureNotificationsTable($conn);

    // Sanitize inputs
    $farmer_id = (int)$farmer_id;
    $title = trim($title);
    $message = trim($message);
    
    // Prepare and execute insert statement
    $stmt = $conn->prepare("INSERT INTO notifications (farmer_id, title, message, status, created_at) VALUES (?, ?, ?, 'unread', NOW())");
    
    if (!$stmt) {
        error_log("Failed to prepare notification insert statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("iss", $farmer_id, $title, $message);
    
    if ($stmt->execute()) {
        $notification_id = $stmt->insert_id;
        $stmt->close();
        return $notification_id;
    } else {
        error_log("Failed to execute notification insert: " . $stmt->error);
        $stmt->close();
        return false;
    }
}

/**
 * Create a notification when a subsidy/assistance application status changes
 * 
 * @param mysqli $conn Database connection object
 * @param int $farmer_id The user_id of the farmer
 * @param string $status The new status (Approved, Rejected, etc.)
 * @param int $application_id Optional: The application ID for reference
 * @return bool|int Returns notification ID on success, false on failure
 */
function createSubsidyStatusNotification($conn, $farmer_id, $status, $application_id = null) {
    $title = "Subsidy Status Update";
    
    // Create appropriate message based on status
    switch ($status) {
        case 'Approved':
            $message = "Your assistance request" . ($application_id ? " (ID: {$application_id})" : "") . " has been APPROVED. You can now claim your assistance.";
            break;
        case 'Rejected':
            $message = "Your assistance request" . ($application_id ? " (ID: {$application_id})" : "") . " has been DENIED. Please contact the office for more information.";
            break;
        case 'Pending':
            $message = "Your assistance request" . ($application_id ? " (ID: {$application_id})" : "") . " is now PENDING review.";
            break;
        default:
            $message = "Your assistance request status has been updated to: {$status}.";
            break;
    }
    
    return createNotification($conn, $farmer_id, $title, $message);
}

?>

