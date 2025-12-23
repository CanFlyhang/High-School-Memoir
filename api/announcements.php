<?php
require 'db.php';

$action = $_GET['action'] ?? '';

if ($action == 'get_latest') {
    // Get latest 2 announcements
    $result = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 2");
    $announcements = [];
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
    echo json_encode(["success" => true, "announcements" => $announcements]);
} elseif ($action == 'get_all') {
    // Get all announcements
    $result = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
    $announcements = [];
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
    echo json_encode(["success" => true, "announcements" => $announcements]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid action"]);
}
?>