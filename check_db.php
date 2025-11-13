<?php
include 'includes/connection.php';

$result = $conn->query('DESCRIBE assistance_applications');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . PHP_EOL;
}
?>
