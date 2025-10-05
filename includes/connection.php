<?php
// Database connection details
$servername = "localhost";
$username_db = "root"; // Replace with your database username
$password_db = "";     // Replace with your database password
$dbname = "cap101"; // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>