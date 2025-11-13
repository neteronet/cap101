<?php
include 'includes/connection.php';

$sql = "CREATE TABLE IF NOT EXISTS subsidy_claims (
    claim_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    application_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    claimer_id INT(11) NOT NULL,
    claim_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (application_id) REFERENCES assistance_applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (claimer_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table subsidy_claims created successfully.";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
