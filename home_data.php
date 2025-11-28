<?php
// Shared data loader for the public landing page (home.php)
// Fetch up to 5 most recent announcements for the landing carousel

include __DIR__ . '/includes/connection.php';

// Check connection
if ($conn->connect_error) {
    // It's crucial to handle connection errors gracefully
    // In a real application, you would log the error and hide the message
    // die("Connection failed: " . $conn->connect_error); 
    // For now, we will just proceed with an empty array if the connection fails
    return; 
}

// ---------------------------------------------------------------------
// 2. Fetch Latest Announcements (Up to 5)
// ---------------------------------------------------------------------

// The column 'image' holds the relative path like 'uploads/announcements/...'
$sql = "SELECT id, title, content, image, publish_date 
        FROM announcements 
        ORDER BY publish_date DESC 
        LIMIT 5";

$result = $conn->query($sql);

if ($result) {
    // Fetch the results into the $landing_announcements array
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            
            // The image column's value is used directly as the 'image_url'
            $image_url = !empty($row['image']) ? htmlspecialchars($row['image']) : '';

            $landing_announcements[] = [
                'id'            => $row['id'],
                'title'         => $row['title'],
                'content'       => $row['content'],
                'publish_date'  => $row['publish_date'],
                'image_url'     => $image_url, 
            ];
        }
    }
    // Free result set
    $result->free();
} else {
    // Handle SQL query error (e.g., table not found)
    // error_log("Announcement query failed: " . $conn->error);
}

// Close the connection
$conn->close();

// The $landing_announcements array is now ready for use in index.php
?>
// Keep connection open for now (home.php may use it later if needed)

