<?php
// connection.php

// Database configuration
$host = 'localhost';
$db   = 'cap101';
$user = 'root';     // Use a secure user in production!
$pass = '';         // Use a strong password in production

// Create connection using MySQLi
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_errno) {
    // Detailed error message
    die("Connection failed: (" . $conn->connect_errno . ") " . $conn->connect_error);
}

// Set character set to UTF-8
if (!$conn->set_charset("utf8mb4")) {
    die("Error loading character set utf8mb4: " . $conn->error);
}

// Optional: You can return or export the $conn if needed
// e.g., return $conn; or global $conn;
?>
