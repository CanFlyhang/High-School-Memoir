<?php
require 'db.php';

$action = $_GET['action'] ?? '';

if ($action == 'list') {
    $result = $conn->query("SELECT * FROM topics ORDER BY created_at DESC");
    $topics = [];
    while ($row = $result->fetch_assoc()) {
        $topics[] = $row;
    }
    echo json_encode(["success" => true, "topics" => $topics]);

} elseif ($action == 'ranking') {
    // Rank topics by number of memoirs associated
    $sql = "
        SELECT t.*, COUNT(m.id) as usage_count
        FROM topics t
        LEFT JOIN memoirs m ON t.id = m.topic_id
        GROUP BY t.id
        ORDER BY usage_count DESC, t.name ASC
        LIMIT 8
    ";
    $result = $conn->query($sql);
    $topics = [];
    while ($row = $result->fetch_assoc()) {
        $topics[] = $row;
    }
    echo json_encode(["success" => true, "topics" => $topics]);

} else {
    echo json_encode(["success" => false, "message" => "Invalid action"]);
}
?>