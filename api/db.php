<?php
// 禁止将 PHP 错误/警告直接输出到响应中，以免破坏 JSON 格式
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', 0);

session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

$servername = "localhost";
$username = "root";
$password = "xzh060822";
$dbname = "high_school_memoir";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
} catch (mysqli_sql_exception $e) {
    die(json_encode(["success" => false, "message" => "数据库连接失败: " . $e->getMessage()]));
}

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");
?>
