<?php
// session_check.php (for farmer only)

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['farmer_id'])) {
    header('Location: farmers-login.php'); // Redirect if not logged in
    exit();
}

// Make session variables available to page
$current_farmer_id = $_SESSION['farmer_id'];
$current_username = $_SESSION['username'];
?>