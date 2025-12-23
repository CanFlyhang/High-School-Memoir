<?php
require 'db.php';

$tables = [];
$result = $conn->query("SHOW TABLES");
if ($result) {
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    echo json_encode(["success" => true, "message" => "数据库连接正常", "tables" => $tables]);
} else {
    echo json_encode(["success" => false, "message" => "无法读取表: " . $conn->error]);
}
?>
