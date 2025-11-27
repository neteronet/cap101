<?php
// Shared data loader for the public landing page (home.php)
// Fetch up to 5 most recent announcements for the landing carousel

include __DIR__ . '/includes/connection.php';

$landing_announcements = [];

if (isset($conn) && !$conn->connect_error) {
    $stmt = $conn->prepare("
        SELECT id, title, content, image_url, publish_date 
        FROM announcements 
        ORDER BY publish_date DESC 
        LIMIT 5
    ");

    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($id, $title, $content, $image_url, $publish_date);
        while ($stmt->fetch()) {
            $landing_announcements[] = [
                'id'           => $id,
                'title'        => $title,
                'content'      => $content,
                'image_url'    => $image_url,
                'publish_date' => $publish_date,
            ];
        }
        $stmt->close();
    }
}

// Keep connection open for now (home.php may use it later if needed)

