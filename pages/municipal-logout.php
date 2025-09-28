<?php
session_start();

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
session_destroy();

// Redirect to the login page (or index.php)
header("location: municipal-login.php"); // Or wherever your login page is
exit;
?>