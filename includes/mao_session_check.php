<?php
// mao_session_check.php (for MAO)
session_start();

if (!isset($_SESSION['mao_id'])) {
    header('Location: municipal-login.php'); // Redirect to MAO login page
    exit();
}

// Fetch MAO details from session
$current_mao_id = $_SESSION['mao_id'];
$current_mao_username = $_SESSION['username'];
?>