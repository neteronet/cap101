<?php
/**
 * One-time script to seed 3 system-related announcements.
 * 
 * Usage:
 * 1. Place this file in the project root (same level as home.php).
 * 2. Visit: http://localhost/cap101-main/seed_announcements.php
 * 3. You should see a simple success message.
 * 4. DELETE this file afterwards for security.
 */

require __DIR__ . '/includes/connection.php';

if (!isset($conn) || $conn->connect_error) {
    die('Database connection error: ' . ($conn->connect_error ?? 'No connection object.'));
}

// Helper: insert announcement if a title does not already exist
function upsert_announcement(mysqli $conn, string $title, string $content, string $publish_date, string $image_url = null) {
    // Check if announcement with this exact title already exists
    $check = $conn->prepare('SELECT id FROM announcements WHERE title = ? LIMIT 1');
    if (!$check) {
        echo 'Failed to prepare check for "' . htmlspecialchars($title) . '": ' . $conn->error . "<br>";
        return;
    }
    $check->bind_param('s', $title);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo 'Skipped (already exists): ' . htmlspecialchars($title) . "<br>";
        $check->close();
        return;
    }
    $check->close();

    // Insert new announcement
    $sql = 'INSERT INTO announcements (title, content, publish_date, image_url) VALUES (?, ?, ?, ?)';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo 'Failed to prepare insert for "' . htmlspecialchars($title) . '": ' . $conn->error . "<br>";
        return;
    }
    $stmt->bind_param('ssss', $title, $content, $publish_date, $image_url);

    if ($stmt->execute()) {
        echo 'Inserted: ' . htmlspecialchars($title) . "<br>";
    } else {
        echo 'Failed to insert "' . htmlspecialchars($title) . '": ' . $stmt->error . "<br>";
    }
    $stmt->close();
}

// 1) Scheduled maintenance
$title1 = 'Scheduled System Maintenance and Data Backup';
$content1 = "To ensure the reliability and security of the Agriconnect system, our team will perform scheduled maintenance and full data backup on October 20, 2025, from 8:00 PM to 10:00 PM.\n\nDuring this time, the system may be temporarily unavailable for logins, subsidy applications, QR-based claiming, and report generation.\n\nPlease finish any critical transactions before the maintenance window. All existing records and applications will remain safe and intact after the update.";
$date1 = '2025-10-20';
$img1 = 'photos/maintenance.jpg'; // You can rename/change this file as needed
upsert_announcement($conn, $title1, $content1, $date1, $img1);

// 2) New OTP-based password recovery feature
$title2 = 'New Feature: Secure OTP-Based Password Recovery';
$content2 = "We have enabled a new Forgot Password feature using One-Time Passwords (OTP) sent to your registered email address.\n\nFarmers, Municipal Agricultural Officers, and Admin users can now safely recover their accounts by requesting an OTP and setting a new password without visiting the office.\n\nTo use this feature, click \"Forgot Password?\" on the login page, enter your email/username, and follow the instructions sent to your inbox.";
$date2 = '2025-10-25';
$img2 = 'photos/otpfeature.jpg'; // Provide this image file in /photos and rename as you like
upsert_announcement($conn, $title2, $content2, $date2, $img2);

// 3) Real-time notifications feature
$title3 = 'Real-Time Notifications for Subsidy Approvals and Claims';
$content3 = "The Agriconnect portal now provides real-time notifications for all subsidy-related updates.\n\nFarmers will receive alerts when their assistance applications are Approved, Rejected, or Successfully Claimed.\n\nLook for the bell icon on your farmer dashboard to view recent notifications, including timestamps, so you always know the current status of your assistance.";
$date3 = '2025-11-05';
$img3 = 'photos/notifications.jpg'; // Provide this image file in /photos and rename as you like
upsert_announcement($conn, $title3, $content3, $date3, $img3);

$conn->close();

echo '<hr><strong>Seeding complete.</strong> You can now visit <a href="home.php">home.php</a> to see the announcements. For security, delete <code>seed_announcements.php</code> when done.';


