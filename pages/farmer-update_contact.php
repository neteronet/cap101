<?php
session_start();
include '../includes/connection.php';

if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("location: farmers-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$new_contact = $_POST['new_contact_number'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($new_contact)) {
    // Sanitize input
    $new_contact = preg_replace('/[^0-9+\-\s()]/', '', $new_contact);
    
    $stmt = $conn->prepare("UPDATE farmers SET contact_number = ? WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $new_contact, $user_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Contact number updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating contact number: " . $stmt->error;
        }
        $stmt->close();
    }
}

$conn->close();
header("location: farmer-my_profile.php");
exit();
?>
